<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$version = parse_ini_file($root . '/beacon/version.properties')['version'];
$runtime = $root . '/dist/beacon-php-runtime-' . $version;
$archive = $runtime . '.tar.gz';
if (file_exists($archive)) { unlink($archive); }
$tar = $runtime . '.tar';
if (file_exists($tar)) { unlink($tar); }
$phar = new PharData($tar);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtime, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isLink()) { throw new RuntimeException('Runtime symlink is not allowed'); }
    if ($file->isFile()) { $phar->addFile($file->getPathname(), basename($runtime) . '/' . substr($file->getPathname(), strlen($runtime) + 1)); }
}
$phar->compress(Phar::GZ);
unset($phar);
unlink($tar);
$library = $root . '/dist/beacon-php-' . $version . '.zip';
if (!is_file($library)) { throw new RuntimeException('Build the Composer library archive before the installer'); }
$template = file_get_contents($root . '/beacon-setup.php');
$installer = str_replace(['@runtime_sha256@', '@agent_sha256@'], [hash_file('sha256', $archive), hash_file('sha256', $library)], $template);
file_put_contents($root . '/dist/beacon-setup.php', $installer);
chmod($root . '/dist/beacon-setup.php', 0755);
printf("Built installer with pinned Agent and runtime SHA-256 (%s).\n", $version);
