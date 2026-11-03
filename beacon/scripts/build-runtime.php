<?php

declare(strict_types=1);

// Build-only tooling. Never execute Composer or a dependency resolver on the target host.
$root = dirname(__DIR__, 2);
require $root . '/beacon/build-tools/vendor/autoload.php';

$version = parse_ini_file($root . '/beacon/version.properties')['version'];
$source = $root . '/dist/.runtime-source';
$output = $root . '/dist/beacon-php-runtime-' . $version;

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (new FilesystemIterator($path) as $file) { removeTree($file->getPathname()); }
    rmdir($path);
}
function copyTree(string $from, string $to): void
{
    if (!is_dir($to)) { mkdir($to, 0755, true); }
    foreach (new FilesystemIterator($from) as $file) {
        if (in_array($file->getFilename(), ['.git', '.github', 'tests', 'test', 'vendor'], true)) { continue; }
        if ($file->isLink()) { throw new RuntimeException('Unexpected source symlink: ' . $file->getPathname()); }
        $target = $to . '/' . $file->getFilename();
        if ($file->isDir()) { copyTree($file->getPathname(), $target); }
        else { copy($file->getPathname(), $target); }
    }
}
removeTree($source);
removeTree($output);
mkdir($source, 0755, true);
$lock = json_decode(file_get_contents($root . '/beacon/runtime/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root . '/beacon/runtime/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$packages = array_column($lock['packages'], null, 'name');
// These packages are provided by the application, never bundled or loaded by the Agent.
$externals = ['laravel/framework', 'symfony/http-kernel', 'guzzlehttp/guzzle', 'guzzlehttp/promises'];
$selected = [];
$queue = array_keys($manifest['require']);
while ($queue !== []) {
    $name = array_shift($queue);
    if (isset($selected[$name]) || !isset($packages[$name]) || in_array($name, $externals, true)) { continue; }
    $selected[$name] = $packages[$name];
    array_push($queue, ...array_keys($packages[$name]['require'] ?? []));
}
ksort($selected);
foreach ($selected as $name => $package) {
    copyTree($root . '/beacon/runtime/vendor/' . $name, $source . '/vendor/' . $name);
}
copyTree($root . '/src', $source . '/app/src');
copyTree($root . '/resources', $source . '/app/resources');
copy($root . '/LICENSE', $source . '/LICENSE');
if (!is_dir($source . '/vendor/composer')) { mkdir($source . '/vendor/composer', 0755, true); }
copy($root . '/beacon/runtime/vendor/composer/InstalledVersions.php', $source . '/vendor/composer/InstalledVersions.php');
copy($root . '/beacon/runtime/vendor/composer/GeneratedServiceProviderData.php', $source . '/vendor/composer/GeneratedServiceProviderData.php');
$installed = require $root . '/beacon/runtime/vendor/composer/installed.php';
$installed['versions'] = array_intersect_key($installed['versions'], $selected + [$manifest['name'] => true]);
$installed['root']['install_path'] = '@ROOT@/app';
foreach ($installed['versions'] as $name => &$data) {
    if (isset($data['install_path'])) { $data['install_path'] = $name === $manifest['name'] ? '@ROOT@/app' : '@ROOT@/vendor/' . $name; }
}
unset($data);
$code = var_export($installed, true);
$code = preg_replace_callback("/'@ROOT@([^']*)'/", static fn (array $m): string => '$root . ' . var_export($m[1], true), $code);
file_put_contents($source . '/vendor/composer/installed.php', '<?php $root = dirname(__DIR__, 2); return ' . $code . ';');
$autoloadFiles = require $root . '/beacon/runtime/vendor/composer/autoload_files.php';
$files = [];
foreach ($autoloadFiles as $file) {
    $relative = substr($file, strlen($root . '/beacon/runtime/'));
    if (is_file($source . '/' . $relative)) { $files[] = $relative; }
}
putenv('BEACON_SCOPE_SOURCE=' . $source);
$command = [PHP_BINARY, $root . '/beacon/build-tools/vendor/bin/php-scoper', 'add-prefix', '--config=' . $root . '/beacon/runtime/scoper.inc.php', '--output-dir=' . $output, '--force', '--no-interaction'];
$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $source);
if (proc_close($process) !== 0) { throw new RuntimeException('Runtime dependency isolation failed'); }
$classmap = Composer\ClassMapGenerator\ClassMapGenerator::createMap($output);
foreach ($classmap as $class => $path) {
    if (!str_starts_with($class, 'BeaconStandalone\\')) { throw new RuntimeException('Unisolated runtime class: ' . $class); }
    $classmap[$class] = substr($path, strlen($output) + 1); }
ksort($classmap);
file_put_contents($output . '/classmap.php', '<?php return ' . var_export($classmap, true) . ';');
file_put_contents($output . '/autoload-files.php', '<?php return ' . var_export($files, true) . ';');
copy($root . '/beacon/runtime/autoload.php', $output . '/autoload.php');
copy($root . '/beacon/runtime/bootstrap.php', $output . '/bootstrap.php');
copy($root . '/beacon/runtime/doctor.php', $output . '/doctor.php');
$provenance = ['schema_version' => 1, 'version' => $version, 'prefix' => 'BeaconStandalone',
    'php' => '^8.2', 'components' => ['curl', 'pdo', 'guzzle', 'laravel', 'symfony'],
    'externals' => $externals, 'security_spec' => json_decode(file_get_contents($root . '/resources/security-contract.json'), true),
    'lock_sha256' => hash_file('sha256', $root . '/beacon/runtime/composer.lock'),
    'packages' => array_map(static fn (array $p): array => ['name' => $p['name'], 'version' => $p['version'], 'reference' => $p['source']['reference'] ?? '', 'license' => $p['license'] ?? []], array_values($selected))];
$provenance['files'] = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile()) { $provenance['files'][substr($file->getPathname(), strlen($output) + 1)] = hash_file('sha256', $file->getPathname()); }
}
ksort($provenance['files']);
file_put_contents($output . '/runtime.json', json_encode($provenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
removeTree($source);
printf("Built isolated Beacon PHP runtime %s (%d packages, %d classes).\n", $version, count($selected), count($classmap));
