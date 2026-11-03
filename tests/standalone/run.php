<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$installer = $root . '/dist/beacon-setup.php';
$native = getenv('BEACON_TEST_EXTENSION_ARCHIVE');
if (!is_file($installer) || $native === false || !is_file($native)) { throw new RuntimeException('Build release artifacts and provide BEACON_TEST_EXTENSION_ARCHIVE'); }
require $installer;
$version = BeaconSetup::AGENT_VERSION;
$base = $root . '/dist/acceptance-' . bin2hex(random_bytes(8));
foreach (['ini', 'extensions', 'offline', 'application/vendor', 'evidence'] as $directory) { mkdir($base . '/' . $directory, 0755, true); }
foreach (array_map('trim', preg_split('/,\s*/', php_ini_scanned_files() ?: '')) as $file) {
    if (!is_file($file)) { continue; }
    $content = file_get_contents($file);
    if (preg_match('/^\s*(?:zend_)?extension\s*=.*(?:opentelemetry|zip)/mi', $content)) { continue; }
    file_put_contents($base . '/ini/' . basename($file), $content);
}
copy($native, $base . '/offline/' . basename($native));
$runtimeName = 'beacon-php-runtime-' . $version . '.tar.gz';
copy($root . '/dist/' . $runtimeName, $base . '/offline/' . $runtimeName);
file_put_contents($base . '/application/prepend.php', '<?php $application_prepend_value = "preserved"; $GLOBALS["application_prepend_calls"] = ($GLOBALS["application_prepend_calls"] ?? 0) + 1;');
file_put_contents($base . '/ini/00-application.ini', 'auto_prepend_file="' . str_replace('\\', '/', $base . '/application/prepend.php') . '"' . "\n");
copy(__DIR__ . '/fixtures/application.php', $base . '/application/index.php');
copy(__DIR__ . '/fixtures/foreign-autoload.php', $base . '/application/vendor/autoload.php');
file_put_contents($base . '/application/composer.json', '{"name":"acceptance/unchanged-app","require":{}}');
file_put_contents($base . '/application/composer.lock', '{"packages":[],"packages-dev":[]}');
$hashes = [];
foreach (['composer.json', 'composer.lock', 'vendor/autoload.php'] as $file) { $hashes[$file] = hash_file('sha256', $base . '/application/' . $file); chmod($base . '/application/' . $file, 0444); }
$environment = array_merge(getenv(), ['PHP_INI_SCAN_DIR' => $base . '/ini', 'OTEL_TRACES_EXPORTER' => 'console', 'OTEL_LOGS_EXPORTER' => 'none', 'OTEL_METRICS_EXPORTER' => 'none',
    'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'true', 'BEACON_SECURITY_OUTPUT' => $base . '/evidence']);
foreach (['COMPOSER_DEV_MODE', 'BEACON_SECURITY_ENABLED', 'BEACON_SECURITY_EVIDENCE_FILE', 'OTEL_PHP_AUTOLOAD_ENABLED', 'OTEL_SDK_DISABLED', 'BEACON_SETUP_DIAGNOSTICS'] as $key) { unset($environment[$key]); }
function run(array $command, array $environment, string $cwd): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $environment);
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($process), $out, $error];
}
function expect(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
$arguments = ['--php-bin=' . PHP_BINARY, '--install-dir=' . $base . '/installed', '--extension-dir=' . $base . '/extensions', '--ini=' . $base . '/ini/99-beacon.ini', '--offline-dir=' . $base . '/offline', '--composer=/nonexistent/composer-must-not-run'];
$command = [PHP_BINARY, $installer, 'install', ...$arguments, '--enable-security', '--service-name=preserved-service'];
$plan = run([...$command, '--dry-run'], $environment, $base . '/application');
expect($plan[0] === 0 && !is_dir($base . '/installed'), 'Dry-run changed installation state: ' . $plan[1] . $plan[2]);
$rollback = run([...$command, '--ini=' . $base . '/inactive.ini'], $environment, $base . '/application');
expect($rollback[0] !== 0 && !is_file($base . '/inactive.ini') && !is_file($base . '/inactive.ini.beacon.json'), 'Failed activation did not roll back INI/state: ' . $rollback[1] . $rollback[2]);
$originalIni = "; Existing application-owned INI content\n";
file_put_contents($base . '/ini/99-beacon.ini', $originalIni);
chmod($base . '/ini/99-beacon.ini', 0640);
$installed = run($command, $environment, $base . '/application');
expect($installed[0] === 0, 'Cold installation failed: ' . $installed[1] . $installed[2]);
if (PHP_OS_FAMILY !== 'Windows') { clearstatcache(); expect((fileperms($base . '/ini/99-beacon.ini') & 0777) === 0640, 'Installation widened original INI permissions'); }
$result = run([PHP_BINARY, $base . '/application/index.php'], $environment, $base . '/application');
expect($result[0] === 0 && str_contains($result[1], 'APPLICATION_OK') && str_contains($result[1], 'PDO::query'), 'Zero-code application failed: ' . $result[1] . $result[2]);
expect($result[2] === '', 'Unexpected startup or hook warning: ' . $result[2]);
foreach ($hashes as $file => $hash) { expect(hash_file('sha256', $base . '/application/' . $file) === $hash, 'Application Composer state changed'); }
$evidence = glob($base . '/evidence/php-*/evidence.jsonl');
expect(count($evidence) === 1, 'Security diagnostic lifecycle did not produce one runtime snapshot');
$body = file_get_contents($evidence[0]);
expect(str_contains($body, 'beacon.security.finding') && str_contains($body, 'beacon.security.sbom.snapshot') && !str_contains($body, 'PRIVATE_STANDALONE_VALUE'), 'Security startup/privacy/SBOM failure');
$doctor = run([PHP_BINARY, $installer, 'doctor', ...$arguments], $environment, $base . '/application');
expect($doctor[0] === 0 && str_contains($doctor[1], '[OK] runtime_loaded'), 'Installed runtime doctor failed: ' . $doctor[1] . $doctor[2]);
$disabled = run([PHP_BINARY, $installer, 'doctor', ...$arguments], $environment + ['OTEL_SDK_DISABLED' => 'true'], $base . '/application');
expect($disabled[0] === 0 && str_contains($disabled[1], '[SKIP] runtime_loaded'), 'Doctor must distinguish intentional SDK disablement from loading');
$again = run([PHP_BINARY, $installer, 'upgrade', ...$arguments], $environment, $base . '/application');
expect($again[0] === 0, 'Idempotent upgrade failed: ' . $again[1] . $again[2]);
$stateObject = json_decode(file_get_contents($base . '/ini/99-beacon.ini.beacon.json'), true);
$settings = json_decode(file_get_contents(dirname($stateObject['launcher']) . '/installation.json'), true);
expect($settings['environment']['OTEL_SERVICE_NAME'] === 'preserved-service', 'Upgrade lost service identity default');
$ini = $base . '/ini/99-beacon.ini'; $before = file_get_contents($ini);
$state = file_get_contents($ini . '.beacon.json');
file_put_contents($base . '/offline/' . $runtimeName, 'corrupted artifact');
$bad = run($command, $environment, $base . '/application');
expect($bad[0] !== 0 && str_contains($bad[2], 'SHA-256 mismatch'), 'Corrupt runtime archive was accepted');
expect(file_get_contents($ini) === $before && file_get_contents($ini . '.beacon.json') === $state, 'Failed upgrade changed active configuration');
copy($root . '/dist/' . $runtimeName, $base . '/offline/' . $runtimeName);
file_put_contents($ini, $before . "; user edit\n");
$edited = run([PHP_BINARY, $installer, 'uninstall', ...$arguments], $environment, $base . '/application');
expect($edited[0] !== 0 && str_contains(file_get_contents($ini), '; user edit'), 'Uninstall overwrote manual configuration edits');
file_put_contents($ini, $before);
$removed = run([PHP_BINARY, $installer, 'uninstall', ...$arguments], $environment, $base . '/application');
expect($removed[0] === 0 && file_get_contents($ini) === $originalIni && !is_file($ini . '.beacon.json'), 'Uninstall did not restore startup configuration');
$probe = run([PHP_BINARY, '-r', 'exit(extension_loaded("opentelemetry") ? 1 : 0);'], $environment, $base . '/application');
expect($probe[0] === 0, 'Uninstall left native instrumentation enabled');
printf("Standalone installation, isolation, prepend chaining, privacy, idempotent upgrade, checksum failure and uninstall passed (%s).\n", $base);
