<?php

declare(strict_types=1);

// Run a fresh process because hooks and Composer autoload files are request-scoped.
if (($argv[1] ?? '') !== '--child') {
    $directory = sys_get_temp_dir() . '/beacon-security-test-' . bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $path = $directory . '/evidence.jsonl';
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child';
    $environment = array_merge(getenv(), ['BEACON_SECURITY_ENABLED' => 'true',
        'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'true', 'BEACON_SECURITY_OUTPUT' => $directory,
        'BEACON_SECURITY_EVIDENCE_FILE' => $path, 'OTEL_PHP_AUTOLOAD_ENABLED' => 'false']);
    unset($environment['COMPOSER_DEV_MODE']);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    $out = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') {
        throw new RuntimeException('Hook child failed: ' . $out . $error);
    }
    $json = file_get_contents($path);
    $events = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($path, FILE_IGNORE_NEW_LINES));
    $findings = array_values(array_filter($events, static fn (array $e): bool => $e['event_name'] === 'beacon.security.finding'));
    $functions = array_column(array_column($findings, 'sink'), 'function');
    foreach (['PDO::query', 'PDO::prepare', 'file_get_contents', 'file_put_contents', 'exec', 'curl_exec'] as $function) {
        if (!in_array($function, $functions, true)) {
            throw new RuntimeException('Missing real hook: ' . $function . ' / ' . implode(',', $functions));
        }
    }
    if (count(array_filter($events, static fn (array $e): bool => $e['event_name'] === 'beacon.security.sbom.snapshot')) === 0
        || str_contains($json, 'PRIVATE_INPUT_7bc1')) {
        throw new RuntimeException('SBOM lifecycle or privacy failure');
    }
    foreach ($findings as $finding) {
        if ($finding['sink']['location'] === '' || str_contains($finding['sink']['location'], '/src/Security/')) {
            throw new RuntimeException('Sink callsite must point to application code');
        }
    }
    if (($eventPath = getenv('BEACON_TEST_HOOK_EVENTS')) !== false) {
        file_put_contents($eventPath, $json);
    }
    if (count(array_filter($functions, static fn (string $f): bool => $f === 'PDO::prepare')) !== 1) {
        throw new RuntimeException('Bound parameter values must not be SQL template evidence');
    }
    // Test artifacts can be retained for schema validation; otherwise clean all diagnostics.
    if (getenv('BEACON_TEST_KEEP') === 'true') {
        echo $path . "\n";
    } else {
        foreach (glob($directory . '/php-*/*') as $file) { unlink($file); }
        foreach (glob($directory . '/php-*') as $dir) { rmdir($dir); }
        unlink($path);
        rmdir($directory);
    }
    echo "Security native hook and shutdown lifecycle tests passed.\n";
    exit;
}

require getenv('BEACON_TEST_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
$_GET = ['q' => 'PRIVATE_INPUT_7bc1'];
$_POST = $_COOKIE = [];
$pdo = new PDO('sqlite::memory:');
if ($pdo->query("SELECT 'PRIVATE_INPUT_7bc1'")->fetchColumn() !== 'PRIVATE_INPUT_7bc1') {
    throw new RuntimeException('Hook changed query result');
}
$statement = $pdo->prepare('SELECT ?');
$statement->execute(['PRIVATE_INPUT_7bc1']); // Binding data must not produce SQL template evidence.
$pdo->prepare("SELECT 'PRIVATE_INPUT_7bc1'");
$path = sys_get_temp_dir() . '/PRIVATE_INPUT_7bc1-' . bin2hex(random_bytes(8));
file_put_contents($path, 'test');
if (file_get_contents($path) !== 'test') { throw new RuntimeException('Hook changed file read'); }
unlink($path);
exec('true', $output, $status);
if ($status !== 0) { throw new RuntimeException('Hook changed command result'); }
$curl = curl_init('http://127.0.0.1:1/PRIVATE_INPUT_7bc1');
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 100]);
curl_exec($curl); // Failed invocations still represent invocation attempts.
$thrown = false;
try { $pdo->query('SELECT PRIVATE_INPUT_7bc1'); } catch (PDOException) { $thrown = true; }
if (!$thrown) { throw new RuntimeException('Hook suppressed the application exception'); }
