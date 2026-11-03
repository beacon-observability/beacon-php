<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

use Beacon\PHP\Version;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;

/** A bounded request/process-local channel, using the application's OTel provider. */
final class Events
{
    private array $identity;
    private LoggerInterface $logger;
    private int $records = 0;
    private bool $reportingFailure = false;
    private mixed $evidence = null;
    private ?string $directory = null;
    public int $apiCalls = 0;
    public int $failures = 0;
    public int $dropped = 0;

    public function __construct(?array $resource = null)
    {
        $this->logger = Globals::loggerProvider()->getLogger('io.beacon.security', Version::VERSION);
        $resource ??= ResourceInfoFactory::defaultResource()->getAttributes()->toArray();
        $name = (string) ($resource['service.name'] ?? 'unknown_service:php');
        $namespace = (string) ($resource['service.namespace'] ?? '');
        $this->identity = [
            'schema_version' => Contract::SCHEMA_VERSION,
            'application_id' => 'app-' . hash('sha256', $namespace . '|' . $name),
            'instance_id' => 'php-' . getmypid() . '-' . bin2hex(random_bytes(16)),
            'service' => (object) array_intersect_key($resource, array_flip([
                'service.name', 'service.namespace', 'service.version', 'service.instance.id', 'deployment.environment.name',
            ])),
            'code' => [
                'repository' => (string) ($resource['vcs.repository.url.full'] ?? ''),
                'commit' => (string) ($resource['vcs.ref.head.revision'] ?? ''),
                'build_id' => (string) ($resource['build.id'] ?? ''),
                'service_version' => (string) ($resource['service.version'] ?? ''),
            ],
            'runtime' => ['language' => 'php', 'implementation' => 'PHP', 'version' => PHP_VERSION,
                'os' => PHP_OS_FAMILY, 'architecture' => php_uname('m'), 'details' => (object) ['sapi' => PHP_SAPI]],
            'identity_status' => str_starts_with($name, 'unknown_service') ? 'fallback' : 'configured',
        ];
        if (Contract::enabled('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED')) {
            $this->openDiagnostics();
        }
    }

    public function applicationId(): string
    {
        return $this->identity['application_id'];
    }

    public function instanceId(): string
    {
        return $this->identity['instance_id'];
    }

    public function emit(string $name, array $payload = [], string $source = 'beacon_security'): void
    {
        // Reserve one final record to explicitly report the bounded channel loss.
        if (++$this->records > 256) {
            ++$this->dropped;
            if ($this->records !== 257) {
                return;
            }
            $name = 'beacon.security.export.dropped';
            $payload = ['reason' => 'process_record_budget', 'dropped_at_least' => 1];
            $source = 'beacon_security';
        }
        $event = $this->identity + ['source' => $source, 'event_name' => $name, 'observed_at' => gmdate('Y-m-d\TH:i:s\Z')] + $payload;
        $body = Contract::json($event);
        if (strlen($body) > 65536) {
            $name = 'beacon.security.export.truncated';
            $body = Contract::json(['schema_version' => 1, 'source' => $source, 'event_name' => $name, 'truncated' => true]);
        }
        try {
            $this->logger->logRecordBuilder()->setSeverityNumber(9)->setSeverityText('INFO')
                ->setEventName($name)->setAttributes(['event.name' => $name, 'source' => $source])->setBody($body)->emit();
            ++$this->apiCalls;
        } catch (\Throwable) {
            ++$this->failures;
        }
        if (is_resource($this->evidence) && @fwrite($this->evidence, $body . "\n") !== strlen($body) + 1) {
            fclose($this->evidence);
            $this->evidence = null;
            $this->snapshotFailed();
        }
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
        $handle = @fopen($path, 'x');
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
        if ($this->reportingFailure) {
            return;
        }
        $this->reportingFailure = true;
        $this->emit('beacon.security.snapshot.failed', ['reason' => 'diagnostic_write_failed']);
        $this->reportingFailure = false;
    }
}
