<?php

declare(strict_types=1);

$directory = sys_get_temp_dir() . '/beacon-security-http-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) { throw new RuntimeException('Cannot reserve HTTP test port'); }
$address = stream_socket_get_name($socket, false);
fclose($socket);
$environment = array_merge(getenv(), ['BEACON_SECURITY_ENABLED' => 'true',
    'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'true', 'BEACON_SECURITY_OUTPUT' => $directory,
    'OTEL_PHP_AUTOLOAD_ENABLED' => 'false', 'OTEL_SDK_DISABLED' => 'false']);
unset($environment['BEACON_SECURITY_EVIDENCE_FILE'], $environment['COMPOSER_DEV_MODE']);
$command = escapeshellarg(PHP_BINARY) . ' -S ' . escapeshellarg($address) . ' ' . escapeshellarg(__DIR__ . '/http-fixture.php');
$process = proc_open($command, [1 => ['file', $directory . '/server.log', 'w'], 2 => ['file', $directory . '/server.log', 'a']], $pipes, null, $environment);
if (!is_resource($process)) { throw new RuntimeException('Cannot start HTTP fixture'); }
try {
    $ready = false;
    for ($i = 0; $i < 100; ++$i) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($connection !== false) { fclose($connection); $ready = true; break; }
        if (!proc_get_status($process)['running']) { break; }
        usleep(20000);
    }
    if (!$ready) { throw new RuntimeException('HTTP fixture did not become ready'); }
    foreach (['PRIVATE_HTTP_VALUE_A', 'PRIVATE_HTTP_VALUE_B'] as $value) {
        $response = file_get_contents('http://' . $address . '/?q=' . $value);
        if ($response !== "ok\n") { throw new RuntimeException('HTTP fixture response changed'); }
    }
    // The HTTP response can precede shutdown callbacks; wait for both complete snapshots.
    for ($i = 0; $i < 100 && count(glob($directory . '/php-*/application.cdx.json')) < 2; ++$i) { usleep(20000); }
    $findings = [];
    foreach (glob($directory . '/php-*/evidence.jsonl') as $file) {
        $body = file_get_contents($file);
        if (str_contains($body, 'PRIVATE_HTTP_VALUE_')) { throw new RuntimeException('HTTP input leaked to evidence'); }
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if ($event['event_name'] === 'beacon.security.finding') { $findings[] = $event; }
        }
    }
    if (count($findings) !== 2 || $findings[0]['finding_id'] !== $findings[1]['finding_id']
        || $findings[0]['instance_id'] === $findings[1]['instance_id']
        || $findings[0]['request']['method'] !== 'GET') {
        throw new RuntimeException('HTTP request isolation or cross-request fingerprint stability failed');
    }
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($directory . '/php-*/*') as $file) { unlink($file); }
    foreach (glob($directory . '/php-*') as $dir) { rmdir($dir); }
    unlink($directory . '/server.log');
    rmdir($directory);
}
echo "Security real HTTP input, request isolation and fingerprint stability tests passed.\n";
