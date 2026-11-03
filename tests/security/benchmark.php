<?php

declare(strict_types=1);

$started = hrtime(true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$startup = (hrtime(true) - $started) / 1e6;
$_GET = ['q' => 'benchmark_marker'];
$pdo = new PDO('sqlite::memory:');
$started = hrtime(true);
// Stay below the finding budget: this measures active collection, not post-budget work.
for ($i = 0; $i < 32; ++$i) {
    $pdo->query("SELECT 'benchmark_marker'")->fetchColumn();
}
$queries = (hrtime(true) - $started) / 1e6;
echo json_encode(['enabled' => getenv('BEACON_SECURITY_ENABLED') ?: 'false',
    'startup_ms' => $startup, 'query_32_ms' => $queries, 'peak_bytes' => memory_get_peak_usage(true)], JSON_THROW_ON_ERROR) . "\n";
