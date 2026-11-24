<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

use Beacon\PHP\Version;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;

/** Bounded Security output, using the application's OTel provider and Resource. */
final class Events
{
    public const MAX_BYTES = 65536;
    private array $identity;
    private array $resource;
    private string $applicationId;
    private LoggerInterface $logger;
    private int $records = 0;
    private mixed $evidence = null;
    private ?string $directory = null;
    private float $lastObserved = 0;
    public int $apiCalls = 0;
    public int $failures = 0;
    public int $dropped = 0;

    public function __construct(?array $resource = null)
    {
        $this->logger = Globals::loggerProvider()->getLogger('io.beacon.security', Version::VERSION);
        $this->resource = $resource ?? ResourceInfoFactory::defaultResource()->getAttributes()->toArray();
        $name = (string) ($this->resource['service.name'] ?? 'unknown_service:php');
        $namespace = (string) ($this->resource['service.namespace'] ?? '');
        $this->applicationId = 'app-' . hash('sha256', $namespace . '|' . $name);
        $this->identity = ['schema_version' => 2, 'instance_id' => Contract::processId()];
        if (Contract::enabled('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED')) { $this->openDiagnostics(); }
    }

    public function applicationId(): string { return $this->applicationId; }
    public function instanceId(): string { return $this->identity['instance_id']; }

    public function event(string $name, array $payload, string $source): array
    {
        return $this->identity + ['source' => $source, 'event_name' => $name,
            'observed_at' => $payload['observed_at'] ?? Contract::timestamp(microtime(true))] + $payload;
    }

    public function wire(array $event): string
    {
        return Contract::json(Siem::normalize(Contract::json($event), $this->resource));
    }

    public function emit(string $name, array $payload = [], string $source = 'beacon_security', ?ContextInterface $context = null): void
    {
        if (!in_array($name, ['beacon.security.finding', 'beacon.security.sbom.inventory'], true)) { return; }
        if ($name === 'beacon.security.finding' && ($payload['attack_evidence']['status'] ?? '') === 'no_evidence'
            && !Contract::enabled('BEACON_SECURITY_EXPORT_NO_EVIDENCE_ENABLED')) { return; }
        $event = $this->event($name, $payload, $source);
        $body = $this->wire($event);
        if (strlen($body) > self::MAX_BYTES && $name === 'beacon.security.finding') {
            foreach (['sources', 'propagation', 'ranges', 'stack'] as $field) { unset($event[$field]); }
            $event['evidence_sampled'] = false;
            $event['evidence_sample_reason'] = 'record_byte_limit';
            $event['truncated'] = true;
            $event['truncation_reason'] = 'record_byte_limit';
            $body = $this->wire($event);
        }
        if (strlen($body) > self::MAX_BYTES || $this->records >= 256) { ++$this->dropped; return; }
        ++$this->records;
        try {
            $this->logger->logRecordBuilder()->setSeverityNumber(9)->setSeverityText('INFO')
                ->setEventName($name)->setAttributes(['event.name' => $name, 'source' => $source])
                ->setContext($context ?? false)->setBody($body)->emit();
            ++$this->apiCalls;
        } catch (\Throwable) { ++$this->failures; }
        if (is_resource($this->evidence) && @fwrite($this->evidence, $body . "\n") !== strlen($body) + 1) {
            fclose($this->evidence);
            $this->evidence = null;
            $this->snapshotFailed();
        }
    }

    /** Validate every final part before emitting any, including escaping and Resource bytes. */
    public function inventory(array $dependencies, array $timing): void
    {
        $now = max(microtime(true), $this->lastObserved + 0.000001);
        $this->lastObserved = $now;
        $base = $timing + ['observed_at' => Contract::timestamp($now)];
        $fits = fn (array $payload): bool => strlen($this->wire($this->event('beacon.security.sbom.inventory', $payload, 'beacon_security_sbom'))) <= self::MAX_BYTES;
        $whole = $base + ['dependencies' => $dependencies];
        if ($fits($whole)) { $parts = [$whole]; }
        else {
            $groups = [[]];
            foreach ($dependencies as $dependency) {
                $last = count($groups) - 1;
                $trial = $base + ['dependencies' => [...$groups[$last], $dependency], 'part_index' => PHP_INT_MAX, 'part_count' => PHP_INT_MAX];
                if (!$fits($trial)) {
                    if ($groups[$last] === []) { throw new \OverflowException('Inventory entry exceeds byte budget'); }
                    $groups[] = [];
                    ++$last;
                }
                $groups[$last][] = $dependency;
            }
            if (count($groups) < 2) { throw new \OverflowException('Inventory exceeds byte budget'); }
            $parts = [];
            foreach ($groups as $index => $group) {
                $part = $base + ['dependencies' => $group, 'part_index' => $index, 'part_count' => count($groups)];
                if (!$fits($part)) { throw new \OverflowException('Inventory entry exceeds byte budget'); }
                $parts[] = $part;
            }
        }
        if ($this->records + count($parts) > 256) { $this->dropped += count($parts); return; }
        foreach ($parts as $part) { $this->emit('beacon.security.sbom.inventory', $part, 'beacon_security_sbom'); }
    }

    public function cyclonedx(array $dependencies, array $reasons): void
    {
        if ($this->directory === null) {
            return;
        }
        $document = ['bomFormat' => 'CycloneDX', 'specVersion' => '1.7', 'version' => 1,
            'metadata' => ['properties' => [
                ['name' => 'source', 'value' => 'beacon_security'],
                ['name' => 'beacon:security:completeness', 'value' => 'partial'],
                ['name' => 'beacon:security:reasons', 'value' => implode(',', $reasons)],
            ]],
            'components' => array_map(static fn (array $dependency): array => [
                'type' => 'library', 'name' => $dependency['name'], 'version' => $dependency['version'],
            ], $dependencies)];
        $path = $this->directory . '/application.cdx.json';
        $handle = @fopen($path, 'w');
        if ($handle === false) {
            $this->snapshotFailed();
            return;
        }
        @chmod($path, 0600);
        $body = Contract::json($document);
        $written = @fwrite($handle, $body);
        fclose($handle);
        if ($written !== strlen($body)) {
            $this->snapshotFailed();
        }
    }

    private function openDiagnostics(): void
    {
        $base = getenv('BEACON_SECURITY_OUTPUT') ?: sys_get_temp_dir() . '/beacon-security';
        // A private unique process directory avoids cross-worker overwrite and symlink reuse.
        $directory = $base . '/php-' . getmypid() . '-' . bin2hex(random_bytes(8));
        if (!@mkdir($directory, 0700, true)) {
            $this->snapshotFailed();
            return;
        }
        $this->directory = $directory;
        $path = getenv('BEACON_SECURITY_EVIDENCE_FILE') ?: $directory . '/evidence.jsonl';
        // An explicit file must be new: diagnostic output is never shared append storage.
        $this->evidence = @fopen($path, 'x');
        if ($this->evidence === false) {
            $this->snapshotFailed();
        } else {
            @chmod($path, 0600);
        }
    }

    private function snapshotFailed(): void
    {
        // Local diagnostic failures never create an extra online event family.
        ++$this->failures;
    }
}
