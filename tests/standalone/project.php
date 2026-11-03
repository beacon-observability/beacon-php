<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$composer = $argv[1] ?? 'composer';
$base = $root . '/dist/project-' . bin2hex(random_bytes(6));
foreach (['ini', 'application', 'offline'] as $dir) { mkdir($base . '/' . $dir, 0755, true); }
foreach (array_map('trim', preg_split('/,\s*/', php_ini_scanned_files() ?: '')) as $file) { if (is_file($file)) { copy($file, $base . '/ini/' . basename($file)); } }
$version = parse_ini_file($root . '/beacon/version.properties')['version'];
copy($root . '/dist/beacon-php-' . $version . '.zip', $base . '/offline/beacon-php-' . $version . '.zip');
file_put_contents($base . '/application/composer.json', '{"name":"beacon/project-acceptance","config":{"allow-plugins":{"php-http/discovery":false,"tbachert/spi":true}}}');
copy($root . '/beacon/tests/emit-pdo-query.php', $base . '/application/index.php');
$environment = array_merge(getenv(), ['PHP_INI_SCAN_DIR' => $base . '/ini', 'OTEL_TRACES_EXPORTER' => 'console', 'OTEL_LOGS_EXPORTER' => 'none', 'OTEL_METRICS_EXPORTER' => 'none']);
foreach (['SUDO_UID', 'COMPOSER_DEV_MODE', 'OTEL_PHP_AUTOLOAD_ENABLED', 'BEACON_SECURITY_ENABLED', 'OTEL_SDK_DISABLED'] as $key) { unset($environment[$key]); }
function command(array $command, array $env, string $cwd): string
{
    $log = $cwd . '/command.log';
    $p = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $cwd, $env);
    $code = proc_close($p); $output = file_get_contents($log);
    if ($code !== 0 || preg_match('/Warning|Fatal error/', $output)) { throw new RuntimeException(implode(' ', $command) . "\n" . $output); }
    return $output;
}
$args = ['--mode=project', '--project=' . $base . '/application', '--install-dir=' . $base . '/installed', '--ini=' . $base . '/ini/99-project.ini', '--offline-dir=' . $base . '/offline', '--composer=' . $composer, '--components=pdo'];
command([PHP_BINARY, $root . '/dist/beacon-setup.php', ...$args, '--skip-extension'], $environment, $base);
if (is_file($base . '/ini/99-project.ini') || is_dir($base . '/installed/instances')) { throw new RuntimeException('Composer-only phase wrote startup configuration'); }
command([PHP_BINARY, $root . '/dist/beacon-setup.php', ...$args, '--skip-composer'], $environment, $base);
$output = command([PHP_BINARY, $base . '/application/index.php', $base . '/application/vendor/autoload.php'], $environment, $base);
if (!str_contains($output, 'PDO::query')) { throw new RuntimeException('Project mode failed to auto-instrument PDO: ' . $output); }
command([PHP_BINARY, $root . '/dist/beacon-setup.php', 'uninstall', ...$args], $environment, $base);
echo "Advanced project Composer-only/startup phases and PDO integration passed ($base).\n";
