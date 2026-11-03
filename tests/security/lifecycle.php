<?php

declare(strict_types=1);

// Configuration gates must work before Composer autoload and across fresh requests.
$base = sys_get_temp_dir() . '/beacon-security-gates-' . bin2hex(random_bytes(8));
mkdir($base, 0700);
foreach ([
    ['BEACON_SECURITY_ENABLED' => 'false'],
    ['BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'false'],
    ['OTEL_SDK_DISABLED' => 'true'],
    ['BEACON_SECURITY_SBOM_ENABLED' => 'false'],
] as $index => $overrides) {
    $directory = $base . '/' . $index;
    mkdir($directory, 0700);
    $path = $directory . '/evidence.jsonl';
    $environment = array_merge(getenv(), ['BEACON_SECURITY_ENABLED' => 'true',
        'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'true', 'BEACON_SECURITY_SBOM_ENABLED' => 'true',
        'BEACON_SECURITY_OUTPUT' => $directory, 'BEACON_SECURITY_EVIDENCE_FILE' => $path,
        'OTEL_PHP_AUTOLOAD_ENABLED' => 'false', 'OTEL_SDK_DISABLED' => 'false'], $overrides);
    unset($environment['COMPOSER_DEV_MODE']);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/hooks.php') . ' --child';
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $err !== '') {
        throw new RuntimeException('Configuration gate child failed: ' . $out . $err);
    }
    if ($index < 3 && glob($directory . '/*') !== []) {
        throw new RuntimeException('Disabled Security, SDK or local output must not write files');
    }
    if ($index === 3) {
        $body = file_get_contents($path);
        if (!str_contains($body, 'beacon.security.finding') || str_contains($body, 'beacon.security.sbom.')
            || glob($directory . '/php-*/application.cdx.json') !== []) {
            throw new RuntimeException('SBOM disable gate failed');
        }
        foreach (glob($directory . '/php-*/*') as $file) { unlink($file); }
        foreach (glob($directory . '/php-*') as $dir) { rmdir($dir); }
        unlink($path);
    }
    rmdir($directory);
}
rmdir($base);
echo "Security enable, SDK disable, local output and SBOM gates passed.\n";
