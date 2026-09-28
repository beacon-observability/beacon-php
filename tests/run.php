<?php

declare(strict_types=1);

use Beacon\PHP\ComponentRegistry;
use Beacon\PHP\ComposerInstaller;
use Beacon\PHP\Diagnostics;

require dirname(__DIR__) . '/vendor/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$components = ComponentRegistry::all();
$assert(isset($components['laravel']), 'Laravel alias must be available');
$assert(isset($components['guzzle']), 'Guzzle alias must be available');
$assert(isset($components['pdo']), 'PDO alias must be available');
$assert(count($components) === count(array_unique($components)), 'Component packages must be unique');
$assert(ComponentRegistry::resolve(['laravel', 'pdo', 'laravel']) === [
    'open-telemetry/opentelemetry-auto-laravel',
    'open-telemetry/opentelemetry-auto-pdo',
], 'Aliases must resolve to deduplicated official packages in input order');

$unknownRejected = false;
try {
    ComponentRegistry::resolve(['not-a-component']);
} catch (InvalidArgumentException) {
    $unknownRejected = true;
}
$assert($unknownRejected, 'Unknown aliases must be rejected');

$installer = new ComposerInstaller('composer');
$command = $installer->command(['open-telemetry/opentelemetry-auto-pdo']);
$assert($command === "'composer' 'require' 'open-telemetry/opentelemetry-auto-pdo' '--no-interaction'", 'Composer command must be deterministic and shell-escaped');

putenv('OTEL_EXPORTER_OTLP_ENDPOINT=https://user:secret@example.test/v1/traces?token=secret');
$report = Diagnostics::collect();
$assert($report['version'] !== '', 'Diagnostics must report a product version');
$assert(count($report['checks']) === 10, 'Diagnostics must report all core checks');
$assert(isset($report['components']['pdo']), 'Diagnostics must report component status');
$serializedReport = json_encode($report, JSON_THROW_ON_ERROR);
$assert(!str_contains($serializedReport, 'secret'), 'Diagnostics must not expose credentials embedded in an endpoint');
putenv('OTEL_EXPORTER_OTLP_ENDPOINT');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

printf("Beacon PHP tests passed (%d components).\n", count($components));
