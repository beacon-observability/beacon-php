<?php

declare(strict_types=1);

use Beacon\PHP\Security\Collector;
use Beacon\PHP\Security\Contract;
use Beacon\PHP\Security\Events;
use Beacon\PHP\Security\Sbom;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// PHP rejects unpaired JSON surrogates. Apply the contract's U+FFFD replacement
// to isolated escapes before decoding, preserving paired surrogate escapes.
$json = file_get_contents(__DIR__ . '/spec/fingerprint-v1.json');
$json = preg_replace_callback('/\\\\u[dD][89aAbB][0-9a-fA-F]{2}\\\\u[dD][c-fC-F][0-9a-fA-F]{2}|\\\\u[dD][89a-fA-F][0-9a-fA-F]{2}/',
    static fn (array $m): string => strlen($m[0]) === 12 ? $m[0] : '\\ufffd', $json);
$vectors = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
foreach ($vectors['vectors'] as $vector) {
    check(Contract::fingerprint($vector['input']) === $vector['expected'], $vector['name']);
}
$input = $vectors['vectors'][0]['input'];
$input['source_signatures'] = ["type|\u{E000}", "type|\u{10000}"];
$a = Contract::fingerprint($input);
$input['source_signatures'] = array_reverse($input['source_signatures']);
check($a === Contract::fingerprint($input), 'UTF-16 ordering must be independent of input order');
$manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/resources/security-contract.json'), true, flags: JSON_THROW_ON_ERROR);
check($manifest['commit'] === Contract::SPEC_COMMIT && $manifest['schema_version'] === Contract::SCHEMA_VERSION
    && $manifest['fingerprint_version'] === Contract::FINGERPRINT_VERSION, 'Artifact provenance must match implemented contract');
check(!Contract::enabled('BEACON_SECURITY_ENABLED'), 'Test suite expects Security disabled by default');
putenv('SECURITY_ENABLED=true');
check(!Contract::enabled('BEACON_SECURITY_ENABLED'), 'Legacy settings must not enable Security');
putenv('SECURITY_ENABLED');

$exporter = new InMemoryExporter();
$provider = LoggerProvider::builder()->addLogRecordProcessor(new SimpleLogRecordProcessor($exporter))->build();
$scope = Configurator::create()->withLoggerProvider($provider)->activate();
$events = new Events(['service.name' => 'orders', 'service.namespace' => 'shop', 'tenant.id' => 'tenant-a']);
check($events->applicationId() === 'app-' . hash('sha256', 'shop|orders'), 'OTel internal fingerprint scope');
$collector = new Collector($events);
$_GET = ['query' => 'PRIVATE_REQUEST_VALUE'];
$_POST = $_COOKIE = [];
for ($i = 0; $i < 2; ++$i) { $collector->observe('PDO::query', 'select PRIVATE_REQUEST_VALUE', 'sql_injection', 'sql_template'); }
$collector->observe('PDO::query', 'select 1', 'sql_injection', 'sql_template');
$collector->observe('exec', 'true', 'command_execution', 'shell_script');
Sbom::snapshot($events);
$records = $exporter->getStorage()->getArrayCopy();
check(count($records) === 3, 'Request deduplication, command observation and inventory');
$envelope = json_decode($records[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
$event = json_decode($envelope['message'], true, flags: JSON_THROW_ON_ERROR);
check($event['sources'][0]['value_length'] === 21 && $event['ranges'][0]['start'] === 7, 'Source metadata and byte ranges');
check($event['attack_evidence']['status'] === 'unknown' && !$event['collection_complete'], 'No vulnerability or completeness claim');
check($event['precision'] === 'conservative' && $event['schema_version'] === 2, 'Evidence schema and precision');
check($envelope['outcome'] === 'unknown' && $envelope['severity'] === 'informational' && $envelope['tenant_id'] === 'tenant-a', 'SIEM semantics');
check($records[0]->getInstrumentationScope()->getName() === 'io.beacon.security', 'Instrumentation scope');
check($records[0]->getEventName() === $event['event_name'], 'Native event name');
check($records[0]->getSeverityNumber() === 9 && $records[0]->getSeverityText() === 'INFO', 'Log severity');
check($records[0]->getAttributes()->get('source') === 'beacon_security', 'Channel attribute');
foreach ($records as $record) { check(!str_contains($record->getBody(), 'PRIVATE_REQUEST_VALUE'), 'Never emit raw values'); }
// Filtering is before record admission; a later eligible occurrence can still be exported.
$event['attack_evidence']['status'] = 'no_evidence';
for ($i = 0; $i < 300; ++$i) { $events->emit('beacon.security.finding', $event); }
check(count($exporter->getStorage()) === 3 && $events->dropped === 0, 'Filtered observations consume no delivery budget');
$event['attack_evidence']['status'] = 'unknown';
$events->emit('beacon.security.finding', $event);
check(count($exporter->getStorage()) === 4, 'Unknown remains eligible');
putenv('BEACON_SECURITY_EXPORT_NO_EVIDENCE_ENABLED=true');
$event['attack_evidence']['status'] = 'no_evidence';
$events->emit('beacon.security.finding', $event);
check(count($exporter->getStorage()) === 5, 'Opt-in no-evidence export');
putenv('BEACON_SECURITY_EXPORT_NO_EVIDENCE_ENABLED');
$before = count($exporter->getStorage());
$events->emit('beacon.security.health');
check(count($exporter->getStorage()) === $before, 'No legacy health events');
// Multipart planning includes escaping, Unicode and all enrichment in the final wire budget.
$dependencies = array_map(static fn (int $i): array => ['name' => $i . str_repeat('"汉😀', 100), 'version' => '1'], range(0, 99));
$events->inventory($dependencies, Contract::processTiming());
$logs = $exporter->getStorage()->getArrayCopy();
$parts = array_slice($logs, $before);
check(count($parts) > 1, 'Multipart inventory');
$assembled = [];
foreach ($parts as $record) {
    check(strlen($record->getBody()) <= 65536, 'Final wire budget');
    $body = json_decode(json_decode($record->getBody(), true)['message'], true);
    array_push($assembled, ...$body['dependencies']);
}
check($assembled === $dependencies, 'Every dependency appears exactly once');
$before = count($logs);
try { $events->inventory([['name' => str_repeat('x', 100000), 'version' => '1']], Contract::processTiming()); throw new RuntimeException('Expected overflow'); }
catch (OverflowException) { check(count($exporter->getStorage()) === $before, 'No partial inventory on oversized entry'); }
$events->inventory([], Contract::processTiming());
$logs = $exporter->getStorage()->getArrayCopy();
if (($path = getenv('BEACON_TEST_EVENTS')) !== false) {
    file_put_contents($path, implode("\n", array_map(static fn ($record): string => $record->getBody(), $logs)) . "\n");
}
putenv('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED=true');
putenv('BEACON_SECURITY_OUTPUT=' . __FILE__ . '/private-diagnostic-path');
$local = new Events(['service.name' => 'orders']);
check($local->failures === 1 && count($exporter->getStorage()) === count($logs), 'Local failure creates no online diagnostic event');
putenv('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED');
putenv('BEACON_SECURITY_OUTPUT');
$scope->detach();
$provider->shutdown();
echo "Security contract, privacy, filtering, SBOM and byte budget tests passed.\n";
