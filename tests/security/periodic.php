<?php

declare(strict_types=1);

if (($argv[1] ?? '') === '--child') {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    usleep(1100000);
    file_get_contents(__FILE__); // A real selected hook observes the elapsed refresh deadline.
    exit;
}
$directory = sys_get_temp_dir() . '/beacon-security-periodic-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$path = $directory . '/evidence.jsonl';
$environment = array_merge(getenv(), [
    'BEACON_SECURITY_ENABLED' => 'true', 'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'true',
    'BEACON_SECURITY_SBOM_REFRESH_SECONDS' => '1', 'BEACON_SECURITY_SBOM_ENABLED' => 'true',
    'BEACON_SECURITY_EVIDENCE_FILE' => $path, 'BEACON_SECURITY_OUTPUT' => $directory,
    'OTEL_PHP_AUTOLOAD_ENABLED' => 'false', 'OTEL_SDK_DISABLED' => 'false',
]);
unset($environment['COMPOSER_DEV_MODE']);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child';
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
try {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') { throw new RuntimeException('Periodic child failed: ' . $output . $error); }
    $records = array_map(static fn (string $line): array => json_decode(json_decode($line, true)['message'], true), file($path, FILE_IGNORE_NEW_LINES));
    if (count($records) < 3) { throw new RuntimeException('Initial, cooperative periodic, and shutdown inventory are required'); }
    foreach ($records as $index => $record) {
        if ($record['event_name'] !== 'beacon.security.sbom.inventory' || $record['instance_id'] !== $records[0]['instance_id']
            || $record['started_at'] !== $records[0]['started_at'] || ($index > 0 && $record['observed_at'] <= $records[$index - 1]['observed_at'])) {
            throw new RuntimeException('Inventory process identity or timing changed');
        }
    }
    if ($records[1]['dependencies'] !== $records[2]['dependencies']) { throw new RuntimeException('Unchanged inventory must be republished'); }
} finally {
    foreach (glob($directory . '/php-*/*') as $file) { unlink($file); }
    foreach (glob($directory . '/php-*') as $dir) { rmdir($dir); }
    if (file_exists($path)) { unlink($path); }
    rmdir($directory);
}
echo "Cooperative inventory refresh, unchanged republication and process timing passed.\n";
