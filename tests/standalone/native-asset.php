<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/beacon-setup.php';
$asset = BeaconSetup::selectAsset(PHP_OS_FAMILY, php_uname('m'), $argv[2] ?? PHP_VERSION, PHP_ZTS === 1);
$destination = ($argv[1] ?? sys_get_temp_dir()) . '/' . $asset['name'];
$cache = getenv('BEACON_TEST_NATIVE_CACHE');
if (!is_file($destination) && $cache !== false && is_file($cache . '/' . $asset['name'])) { copy($cache . '/' . $asset['name'], $destination); }
if (!is_file($destination)) {
    $url = BeaconSetup::EXTENSION_REPOSITORY . '/releases/download/' . BeaconSetup::EXTENSION_TAG . '/' . $asset['name'];
    $process = proc_open(['curl', '--fail', '--location', '--retry', '3', '--output', $destination, $url], [STDIN, STDERR, STDERR], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) { throw new RuntimeException('Native artifact download failed'); }
}
if (hash_file('sha256', $destination) !== $asset['sha256']) { throw new RuntimeException('Native artifact checksum mismatch'); }
echo $destination . "\n";
