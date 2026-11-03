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
$events = new Events(['service.name' => 'orders', 'service.namespace' => 'shop']);
check($events->applicationId() === 'app-' . hash('sha256', 'shop|orders'), 'OTel application identity');
$collector = new Collector($events);
$_GET = ['query' => 'PRIVATE_REQUEST_VALUE'];
$_POST = $_COOKIE = [];
$collector->observe('PDO::query', 'select PRIVATE_REQUEST_VALUE', 'sql_injection', 'sql_template');
$collector->observe('PDO::query', 'select 1', 'sql_injection', 'sql_template');
$collector->observe('exec', 'true', 'command_execution', 'shell_script');
Sbom::snapshot($events);
$records = $exporter->getStorage()->getArrayCopy();
check(count($records) >= 3, 'Finding and SBOM must be emitted');
$event = json_decode($records[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
check($event['sources'][0]['value_length'] === 21 && $event['ranges'][0]['start'] === 7, 'Source metadata and byte ranges');
check($event['assessment'] === 'candidate_risk' && $event['validation'] === 'unvalidated', 'No vulnerability confirmation');
check($event['precision'] === 'conservative' && $event['execution_observation'] === 'invocation_attempt', 'No execution or taint claim');
check($records[0]->getInstrumentationScope()->getName() === 'io.beacon.security', 'Instrumentation scope');
check($records[0]->getEventName() === $event['event_name'], 'Native event name');
check($records[0]->getSeverityNumber() === 9 && $records[0]->getSeverityText() === 'INFO', 'Log severity');
check($records[0]->getAttributes()->get('source') === 'beacon_security', 'Channel attribute');
foreach ($records as $record) {
    check(!str_contains($record->getBody(), 'PRIVATE_REQUEST_VALUE'), 'Never emit raw request or sink values');
}
check(json_decode($records[1]->getBody(), true)['assessment'] === 'observation', 'Uncorrelated command execution is an observation');
$_GET = ['too_large' => str_repeat('a', 4097)];
$collector->observe('PDO::query', 'select 1', 'sql_injection', 'sql_template');
$logs = $exporter->getStorage()->getArrayCopy();
check(end($logs)->getEventName() === 'beacon.security.collection.incomplete', 'Oversized source must report incomplete collection');
$events->emit('beacon.security.health', ['large' => str_repeat('a', 65536)]);
$logs = $exporter->getStorage()->getArrayCopy();
check(json_decode(end($logs)->getBody(), true)['truncated'] === true, 'Record byte budget');
for ($i = 0; $i < 300; ++$i) {
    $events->emit('beacon.security.health');
}
$logs = $exporter->getStorage()->getArrayCopy();
check(count($logs) === 257 && end($logs)->getEventName() === 'beacon.security.export.dropped', 'Channel count budget and loss event');
// Keep events for independent JSON Schema validation, with all byte budgets applied.
if (($path = getenv('BEACON_TEST_EVENTS')) !== false) {
    file_put_contents($path, implode("\n", array_map(static fn ($r): string => $r->getBody(), $logs)) . "\n");
}
// Failed local snapshots must be observable without exposing the configured path.
putenv('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED=true');
putenv('BEACON_SECURITY_OUTPUT=' . __FILE__ . '/private-diagnostic-path');
new Events(['service.name' => 'orders']);
$logs = $exporter->getStorage()->getArrayCopy();
check(end($logs)->getEventName() === 'beacon.security.snapshot.failed', 'Diagnostic write failure event');
check(!str_contains(end($logs)->getBody(), 'private-diagnostic-path'), 'Do not export local paths in diagnostics');
putenv('BEACON_SECURITY_LOCAL_OUTPUT_ENABLED');
putenv('BEACON_SECURITY_OUTPUT');
$scope->detach();
$provider->shutdown();
echo "Security contract, privacy, SBOM and budget tests passed.\n";
