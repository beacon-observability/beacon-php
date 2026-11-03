<?php

declare(strict_types=1);

$metadata = json_decode(file_get_contents(__DIR__ . '/runtime.json'), true, flags: JSON_THROW_ON_ERROR);
$status = defined('BEACON_STANDALONE_STATUS') ? BEACON_STANDALONE_STATUS : 'missing';
$checks = [
    'php' => PHP_VERSION_ID >= 80200,
    'extension' => extension_loaded('opentelemetry'),
    'beacon_extension' => defined('OpenTelemetry\\Instrumentation\\BEACON_DISTRIBUTION') && constant('OpenTelemetry\\Instrumentation\\BEACON_DISTRIBUTION') === 'Beacon',
    'bootstrap' => in_array($status, ['ready', 'disabled'], true),
    'runtime_loaded' => $status === 'ready' && class_exists('BeaconStandalone\\Beacon\\PHP\\Standalone\\Runtime', false),
];
if (in_array('--json', $argv, true)) {
    echo json_encode(['version' => $metadata['version'], 'mode' => 'standalone', 'status' => $status, 'checks' => $checks], JSON_THROW_ON_ERROR) . "\n";
} else {
    echo 'Beacon PHP ' . $metadata['version'] . ' (standalone, ' . $status . ")\n";
    foreach ($checks as $name => $ok) {
        echo ($status === 'disabled' && $name === 'runtime_loaded' ? '[SKIP] ' : ($ok ? '[OK] ' : '[FAIL] ')) . $name . "\n";
    }
}
if ($status === 'disabled') { unset($checks['runtime_loaded']); }
exit(in_array(false, $checks, true) ? 1 : 0);
