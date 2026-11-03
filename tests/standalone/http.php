<?php

declare(strict_types=1);

// Real framework/client dependencies are build/test inputs, never part of the shipped runtime.
$root = dirname(__DIR__, 2);
$version = parse_ini_file($root . '/beacon/version.properties')['version'];
$base = $root . '/dist/http-' . bin2hex(random_bytes(6));
foreach (['public', 'bootstrap/cache', 'storage/logs', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'resources/views', 'config'] as $dir) { mkdir($base . '/' . $dir, 0755, true); }
file_put_contents($base . '/composer.json', json_encode(['name' => 'beacon/framework-acceptance', 'autoload' => ['psr-4' => ['App\\' => 'app/']]]));
copy(__DIR__ . '/fixtures/http.php', $base . '/public/index.php');
file_put_contents($base . '/config/app.php', '<?php return ["key" => "base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=", "cipher" => "AES-256-CBC", "debug" => true];');
$endpoint = $argv[1] ?? 'http://127.0.0.1:4318';
$environment = array_merge(getenv(), ['BEACON_TEST_APP_AUTOLOAD' => $root . '/beacon/runtime/vendor/autoload.php', 'BEACON_SECURITY_ENABLED' => 'true',
    'OTEL_SERVICE_NAME' => 'beacon-standalone-http-validation', 'OTEL_TRACES_EXPORTER' => 'otlp', 'OTEL_LOGS_EXPORTER' => 'otlp',
    'OTEL_EXPORTER_OTLP_ENDPOINT' => $endpoint, 'OTEL_EXPORTER_OTLP_PROTOCOL' => 'http/protobuf', 'OTEL_METRICS_EXPORTER' => 'none',
    'OTEL_BSP_SCHEDULE_DELAY' => '1', 'OTEL_BSP_MAX_EXPORT_BATCH_SIZE' => '1', 'OTEL_BSP_MAX_QUEUE_SIZE' => '16']);
// Ask the OS for a free loopback port. Server startup is checked before every request.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$log = $base . '/server.log';
$process = proc_open([PHP_BINARY, '-d', 'auto_prepend_file=' . $root . '/dist/beacon-php-runtime-' . $version . '/bootstrap.php', '-S', $address, '-t', $base . '/public'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $base, $environment);
try {
    for ($i = 0; $i < 100; ++$i) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($connection !== false) { fclose($connection); break; }
        if (!proc_get_status($process)['running']) { throw new RuntimeException(file_get_contents($log)); }
        usleep(50000);
    }
    foreach (['plain', 'symfony', 'laravel'] as $framework) {
        if ($framework !== 'plain') { copy($root . '/beacon/runtime/composer.lock', $base . '/composer.lock'); }
        $context = stream_context_create(['http' => ['header' => "traceparent: 00-12345678901234567890123456789012-1234567890123456-01\r\n", 'timeout' => 30, 'ignore_errors' => true]]);
        $body = file_get_contents('http://' . $address . '/index.php?' . $framework . '=1', false, $context);
        if ($body !== 'HTTP_APPLICATION_OK') { throw new RuntimeException($framework . ' changed response: ' . substr($body, 0, 3000) . "\n" . file_get_contents($log)); }
    }
    usleep(300000);
} finally { proc_terminate($process); proc_close($process); }
$errors = file_get_contents($log);
if (preg_match('/Warning|Fatal|Deprecated|initialization failed|Export failure|not found in descriptor pool/i', $errors)) { throw new RuntimeException($errors); }
echo "Plain PHP, Symfony, Laravel, PDO and Guzzle HTTP behavior passed; verify Collector receipt separately ($base).\n";
