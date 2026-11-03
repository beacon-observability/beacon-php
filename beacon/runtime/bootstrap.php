<?php

declare(strict_types=1);

$beaconPreviousPrepend = (static function (): ?string {
    $settings = [];
    $configFile = __DIR__ . '/installation.json';
    if (is_file($configFile)) { $settings = json_decode((string) file_get_contents($configFile), true) ?: []; }
    $previous = $settings['previous_prepend'] ?? null;
    if (in_array(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), ['beacon-setup.php', 'composer', 'composer.phar'], true)) { return null; }
    if (defined('BEACON_STANDALONE_LOADED')) { return null; }
    define('BEACON_STANDALONE_LOADED', true);
    $restore = [];
    $defaults = ['OTEL_PHP_AUTOLOAD_ENABLED' => 'true', 'OTEL_EXPORTER_OTLP_PROTOCOL' => 'http/protobuf', 'OTEL_METRICS_EXPORTER' => 'none'];
    $defaults = ($settings['environment'] ?? []) + $defaults;
    if ($settings['security_enabled'] ?? false) { $defaults['BEACON_SECURITY_ENABLED'] = 'true'; }
    if (getenv('BEACON_SETUP_DIAGNOSTICS') === '1') {
        $defaults = ['OTEL_TRACES_EXPORTER' => 'none', 'OTEL_LOGS_EXPORTER' => 'none', 'OTEL_METRICS_EXPORTER' => 'none', 'BEACON_SECURITY_ENABLED' => 'false', 'BEACON_SECURITY_LOCAL_OUTPUT_ENABLED' => 'false'] + $defaults;
    }
    try {
        if (!in_array(strtolower((string) getenv('OTEL_SDK_DISABLED')), ['true', '1', 'yes', 'on'], true)) {
            foreach ($defaults as $key => $value) {
                $old = getenv($key);
                if ($old === false || $key === 'OTEL_PHP_AUTOLOAD_ENABLED' || getenv('BEACON_SETUP_DIAGNOSTICS') === '1') {
                    $restore[$key] = $old;
                    putenv($key . '=' . $value);
                }
            }
            $runtime = $settings['runtime_dir'] ?? __DIR__;
            define('BEACON_STANDALONE_RUNTIME_ROOT', realpath($runtime));
            $GLOBALS['beacon.standalone.application_packages'] = static function (): array {
                if (!class_exists(\Composer\Autoload\ClassLoader::class, false)
                    || !class_exists(\Composer\InstalledVersions::class)) { return ['packages' => [], 'truncated' => false]; }
                $records = [];
                foreach (\Composer\InstalledVersions::getAllRawData() as $data) {
                    foreach ($data['versions'] ?? [] as $name => $package) {
                        if (count($records) >= 2048) { return ['packages' => $records, 'truncated' => true]; }
                        if (isset($package['install_path'])) {
                            $records[] = ['name' => $name, 'version' => $package['pretty_version'] ?? '', 'path' => $package['install_path']];
                        }
                    }
                }
                return ['packages' => $records, 'truncated' => false];
            };
            require $runtime . '/autoload.php';
            \BeaconStandalone\Beacon\PHP\Security\Runtime::boot();
            \BeaconStandalone\OpenTelemetry\API\Globals::tracerProvider();
            \BeaconStandalone\Beacon\PHP\Standalone\Runtime::boot();
            define('BEACON_STANDALONE_STATUS', 'ready');
        } else { define('BEACON_STANDALONE_STATUS', 'disabled'); }
    } catch (Throwable $error) {
        define('BEACON_STANDALONE_STATUS', 'failed');
        error_log('Beacon PHP initialization failed (' . get_class($error) . ')');
    } finally {
        foreach ($restore as $key => $value) { $value === false ? putenv($key) : putenv($key . '=' . $value); }
    }
    return getenv('BEACON_SETUP_DIAGNOSTICS') === '1' ? null : $previous;
})();

// Execute an existing prepend in the global script scope, preserving its variables and behavior.
if (is_string($beaconPreviousPrepend) && $beaconPreviousPrepend !== '' && realpath($beaconPreviousPrepend) !== __FILE__) {
    require $beaconPreviousPrepend;
}
unset($beaconPreviousPrepend);
