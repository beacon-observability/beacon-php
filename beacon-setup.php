#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone installer for the Beacon PHP distribution.
 *
 * Keep this file dependency-free: it is published as an individual release
 * asset and must run before Composer dependencies are installed.
 */
final class BeaconSetup
{
    public const AGENT_VERSION = '1.3.1';
    public const AGENT_REPOSITORY = 'https://github.com/beacon-observability/beacon-php';
    public const RUNTIME_SHA256 = '@runtime_sha256@';
    public const AGENT_SHA256 = '@agent_sha256@';
    public const EXTENSION_VERSION = '1.1.0';
    public const EXTENSION_TAG = 'v1.1.0';
    public const EXTENSION_REPOSITORY = 'https://github.com/beacon-observability/beacon-php-instrumentation';
    public const INI_FILENAME = '99-beacon-opentelemetry.ini';
    public const INI_MARKER = '; Managed by Beacon PHP setup';

    /** @var array<string, string> */
    public const EXTENSION_ASSETS = [
        'beacon-php-instrumentation-1.1.0-php8.2-nts-linux-arm64.tar.gz' => '05d25043c310f2c017086494f9d0afcf8701a254927ad2c60f2504efe2e4b668',
        'beacon-php-instrumentation-1.1.0-php8.2-nts-linux-x86_64.tar.gz' => '54ac5c97b008d614e8ad51a334ff1f3004fe40457789dd14e476a645455cb3e6',
        'beacon-php-instrumentation-1.1.0-php8.2-nts-macos-arm64.tar.gz' => '5b3bd0c51e5099d614c1e1b3f4b79b22e55cebff8cf0e35c175aa826c8b41878',
        'beacon-php-instrumentation-1.1.0-php8.2-nts-vs16-x86_64.zip' => 'c87368929417e10417a7179ce89052cec3b38ec07d6809390f7c27f794c34821',
        'beacon-php-instrumentation-1.1.0-php8.2-ts-vs16-x86_64.zip' => 'ccfca18117b0eded846d03a628c63b5b0fcf8ddede5d6b7f783ad643c1025e5d',
        'beacon-php-instrumentation-1.1.0-php8.3-nts-linux-arm64.tar.gz' => 'a8f5619a38e31f81f4bda98616371bb2a249778a67c8776986e92c081603bf78',
        'beacon-php-instrumentation-1.1.0-php8.3-nts-linux-x86_64.tar.gz' => 'caff1d133d3c330e9e5614fe979ac938f8a6fc6631cdcccdb7a0542f1ee190fd',
        'beacon-php-instrumentation-1.1.0-php8.3-nts-macos-arm64.tar.gz' => '9157df6b13c23cd9c8a158bab56d745d6ebf75015c2940547d513e3d7d4d880a',
        'beacon-php-instrumentation-1.1.0-php8.3-nts-vs16-x86_64.zip' => 'd843c438cc997df9d9ad5303a3b0467fa7dd413955a253c7b48e2606652bdd42',
        'beacon-php-instrumentation-1.1.0-php8.3-ts-vs16-x86_64.zip' => 'ce9432933aa662b67bc855157fc02c37c45b0975c188a35e5d3638dc2bed0669',
        'beacon-php-instrumentation-1.1.0-php8.4-nts-linux-arm64.tar.gz' => '9f3ea1035e245b264df25f60b7ebb56550bdc3d8767ea9af50023a11ba9872c5',
        'beacon-php-instrumentation-1.1.0-php8.4-nts-linux-x86_64.tar.gz' => '5d330e35d895a300c0d5754e234d4e2289477d55de828f547880d05f2ddb94d0',
        'beacon-php-instrumentation-1.1.0-php8.4-nts-macos-arm64.tar.gz' => '61b122229970c048bac3cc5099d55eac00fd673ad5afb2c552c0eca3bde5c9b7',
        'beacon-php-instrumentation-1.1.0-php8.4-nts-vs17-x86_64.zip' => 'db1921c44d7176018ac916aa8912e1312f2cdfe602c2488df2851c8fee7f90d8',
        'beacon-php-instrumentation-1.1.0-php8.4-ts-vs17-x86_64.zip' => 'dbe457246c7dd7c17c5419379f572f81a7cb6abe88edc02f77e070dbae06dfbe',
    ];

    /** @var array<string, string> */
    public const COMPONENT_PACKAGES = [
        'aws-sdk' => 'open-telemetry/opentelemetry-auto-aws-sdk',
        'cakephp' => 'open-telemetry/opentelemetry-auto-cakephp',
        'codeigniter' => 'open-telemetry/opentelemetry-auto-codeigniter',
        'curl' => 'open-telemetry/opentelemetry-auto-curl',
        'doctrine' => 'open-telemetry/opentelemetry-auto-doctrine',
        'ext-amqp' => 'open-telemetry/opentelemetry-auto-ext-amqp',
        'ext-rdkafka' => 'open-telemetry/opentelemetry-auto-ext-rdkafka',
        'guzzle' => 'open-telemetry/opentelemetry-auto-guzzle',
        'http-async' => 'open-telemetry/opentelemetry-auto-http-async',
        'io' => 'open-telemetry/opentelemetry-auto-io',
        'laravel' => 'open-telemetry/opentelemetry-auto-laravel',
        'magento2' => 'open-telemetry/opentelemetry-auto-magento2',
        'mongodb' => 'open-telemetry/opentelemetry-auto-mongodb',
        'mysqli' => 'open-telemetry/opentelemetry-auto-mysqli',
        'openai-php' => 'open-telemetry/opentelemetry-auto-openai-php',
        'pdo' => 'open-telemetry/opentelemetry-auto-pdo',
        'php-session' => 'open-telemetry/opentelemetry-auto-php-session',
        'psr3' => 'open-telemetry/opentelemetry-auto-psr3',
        'psr6' => 'open-telemetry/opentelemetry-auto-psr6',
        'psr14' => 'open-telemetry/opentelemetry-auto-psr14',
        'psr15' => 'open-telemetry/opentelemetry-auto-psr15',
        'psr16' => 'open-telemetry/opentelemetry-auto-psr16',
        'psr18' => 'open-telemetry/opentelemetry-auto-psr18',
        'reactphp' => 'open-telemetry/opentelemetry-auto-reactphp',
        'slim' => 'open-telemetry/opentelemetry-auto-slim',
        'symfony' => 'open-telemetry/opentelemetry-auto-symfony',
        'wordpress' => 'open-telemetry/opentelemetry-auto-wordpress',
        'yii' => 'open-telemetry/opentelemetry-auto-yii',
    ];

    /** @var array<string, string> */
    private const COMPONENT_DETECTION = [
        'aws/aws-sdk-php' => 'aws-sdk',
        'cakephp/cakephp' => 'cakephp',
        'codeigniter4/framework' => 'codeigniter',
        'doctrine/dbal' => 'doctrine',
        'doctrine/orm' => 'doctrine',
        'ext-amqp' => 'ext-amqp',
        'ext-curl' => 'curl',
        'ext-mongodb' => 'mongodb',
        'ext-mysqli' => 'mysqli',
        'ext-pdo' => 'pdo',
        'ext-rdkafka' => 'ext-rdkafka',
        'guzzlehttp/guzzle' => 'guzzle',
        'laravel/framework' => 'laravel',
        'magento/product-community-edition' => 'magento2',
        'magento/product-enterprise-edition' => 'magento2',
        'mongodb/mongodb' => 'mongodb',
        'openai-php/client' => 'openai-php',
        'react/event-loop' => 'reactphp',
        'react/http' => 'reactphp',
        'slim/slim' => 'slim',
        'symfony/framework-bundle' => 'symfony',
        'yiisoft/yii2' => 'yii',
    ];

    /** @var array<string, string> */
    private const EXTENSION_COMPONENT_DETECTION = [
        'amqp' => 'ext-amqp',
        'curl' => 'curl',
        'mongodb' => 'mongodb',
        'mysqli' => 'mysqli',
        'pdo' => 'pdo',
        'rdkafka' => 'ext-rdkafka',
    ];

    /** @var array<string, string> */
    private const PHP_API_BY_VERSION = [
        '8.2' => '20220829',
        '8.3' => '20230831',
        '8.4' => '20240924',
    ];

    /** @param list<string> $argv */
    public static function main(array $argv): int
    {
        try {
            $options = self::parseArguments(array_slice($argv, 1));
            if ($options['help']) {
                self::printHelp();
                return 0;
            }

            if ($options['uninstall']) {
                return self::uninstall($options);
            }

            return self::install($options);
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
            return 1;
        }
    }

    /** @param list<string> $arguments @return array<string, mixed> */
    public static function parseArguments(array $arguments): array
    {
        $options = ['help' => false, 'action' => 'install', 'mode' => 'standalone', 'uninstall' => false,
            'dry-run' => false, 'skip-composer' => false, 'skip-extension' => false,
            'php-bins' => [], 'project' => getcwd() ?: '.', 'components' => 'auto', 'composer' => 'composer',
            'offline-dir' => null, 'extension-dir' => null, 'ini' => null,
            'install-dir' => PHP_OS_FAMILY === 'Windows' ? (getenv('ProgramData') ?: 'C:/ProgramData') . '/Beacon/PHP' : '/opt/beacon/php',
            'enable-security' => null, 'service-name' => null, 'otlp-endpoint' => null];
        if ($arguments !== [] && in_array($arguments[0], ['install', 'upgrade', 'uninstall', 'doctor'], true)) { $options['action'] = array_shift($arguments); }
        $flags = ['dry-run', 'skip-composer', 'skip-extension'];
        $values = ['mode', 'project', 'components', 'composer', 'offline-dir', 'extension-dir', 'ini', 'install-dir', 'service-name', 'otlp-endpoint'];
        for ($i = 0; $i < count($arguments); ++$i) {
            $arg = $arguments[$i];
            if (in_array($arg, ['-h', '--help'], true)) { $options['help'] = true; continue; }
            if ($arg === '--uninstall') { $options['action'] = 'uninstall'; continue; }
            if ($arg === '--enable-security' || $arg === '--disable-security') { $options['enable-security'] = $arg === '--enable-security'; continue; }
            if (!str_starts_with($arg, '--')) { throw new InvalidArgumentException('Unexpected argument: ' . $arg); }
            [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
            if (in_array($name, $flags, true) && $value === null) { $options[$name] = true; continue; }
            if ($name !== 'php-bin' && !in_array($name, $values, true)) { throw new InvalidArgumentException('Unknown option: --' . $name); }
            $value ??= $arguments[++$i] ?? null;
            if ($value === null || $value === '' || str_starts_with($value, '--')) { throw new InvalidArgumentException('--' . $name . ' requires a value'); }
            if ($name === 'php-bin') { $options['php-bins'][] = $value; } else { $options[$name] = $value; }
        }
        $options['php-bins'] = $options['php-bins'] ?: [PHP_BINARY];
        if (is_string($options['otlp-endpoint'])) {
            $parts = parse_url($options['otlp-endpoint']);
            if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || (isset($parts['user']) || isset($parts['pass'])) || (isset($parts['query']) || isset($parts['fragment']))) {
                throw new InvalidArgumentException('Use an HTTP(S) endpoint without credentials/query; configure authentication through OTEL environment variables');
            }
        }
        $options['uninstall'] = $options['action'] === 'uninstall';
        if (!in_array($options['mode'], ['standalone', 'project'], true)) { throw new InvalidArgumentException('--mode must be standalone or project'); }
        if (($options['ini'] !== null || $options['extension-dir'] !== null) && count($options['php-bins']) !== 1) { throw new InvalidArgumentException('Custom INI/extension paths require one --php-bin target'); }
        if ($options['skip-composer'] && $options['skip-extension']) { throw new InvalidArgumentException('Both installation phases cannot be skipped'); }
        if ($options['mode'] === 'standalone' && ($options['components'] !== 'auto' || $options['skip-composer'] || $options['skip-extension'])) { throw new InvalidArgumentException('--components, --skip-composer and --skip-extension apply only to --mode=project'); }
        return $options;
    }

    /** @return array{name: string, sha256: string, extension-file: string, installed-file: string} */
    public static function selectAsset(string $osFamily, string $architecture, string $phpVersion, bool $threadSafe, bool $musl = false): array
    {
        if (!preg_match('/^(8\.[234])(?:\.|$)/', $phpVersion, $matches)) {
            throw new RuntimeException(sprintf('PHP %s is unsupported; Beacon binaries cover PHP 8.2, 8.3, and 8.4', $phpVersion));
        }
        $php = $matches[1];
        $architecture = self::normalizeArchitecture($architecture);
        $mode = $threadSafe ? 'ts' : 'nts';

        $platform = match (strtolower($osFamily)) {
            'linux' => self::linuxPlatform($architecture, $threadSafe, $musl),
            'darwin', 'macos' => self::macPlatform($architecture, $threadSafe),
            'windows' => self::windowsPlatform($architecture),
            default => throw new RuntimeException(sprintf('Unsupported operating system: %s', $osFamily)),
        };
        $suffix = strtolower($osFamily) === 'windows' ? '.zip' : '.tar.gz';
        $name = sprintf(
            'beacon-php-instrumentation-%s-php%s-%s-%s%s',
            self::EXTENSION_VERSION,
            $php,
            $mode,
            $platform,
            $suffix,
        );
        $sha256 = self::EXTENSION_ASSETS[$name] ?? null;
        if ($sha256 === null) {
            throw new RuntimeException(sprintf('No released Beacon extension asset matches %s', $name));
        }

        return [
            'name' => $name,
            'sha256' => $sha256,
            'extension-file' => strtolower($osFamily) === 'windows' ? 'php_opentelemetry.dll' : 'opentelemetry.so',
            'installed-file' => strtolower($osFamily) === 'windows'
                ? 'php_opentelemetry_beacon_' . str_replace('.', '_', self::EXTENSION_VERSION) . '.dll'
                : 'opentelemetry-beacon-' . self::EXTENSION_VERSION . '.so',
        ];
    }

    /** @return list<string> */
    public static function detectComponentAliases(string $project): array
    {
        $packageNames = [];
        $composerPath = rtrim($project, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composerPath)) {
            throw new RuntimeException(sprintf('Composer project not found: %s', $composerPath));
        }
        $composer = self::readJsonObject($composerPath);
        foreach (['require', 'require-dev'] as $section) {
            foreach (array_keys(is_array($composer[$section] ?? null) ? $composer[$section] : []) as $package) {
                $packageNames[strtolower((string) $package)] = true;
            }
        }

        $lockPath = rtrim($project, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.lock';
        if (is_file($lockPath)) {
            $lock = self::readJsonObject($lockPath);
            foreach (['packages', 'packages-dev'] as $section) {
                foreach (is_array($lock[$section] ?? null) ? $lock[$section] : [] as $package) {
                    if (is_array($package) && is_string($package['name'] ?? null)) {
                        $packageNames[strtolower($package['name'])] = true;
                    }
                }
            }
        }

        $detected = [];
        foreach (self::COMPONENT_DETECTION as $package => $alias) {
            if (isset($packageNames[$package])) {
                $detected[$alias] = true;
            }
        }

        $aliases = [];
        foreach (array_keys(self::COMPONENT_PACKAGES) as $alias) {
            if (isset($detected[$alias])) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    /** @param array<string, mixed> $options */
    private static function install(array $options): int
    {
        if ($options['action'] === 'doctor') { return self::doctor($options); }
        if ($options['mode'] === 'project' && !$options['skip-composer'] && getenv('SUDO_UID') !== false) {
            throw new RuntimeException('Run project Composer installation as the application owner; use standalone mode for system installation.');
        }
        $composerOnly = $options['mode'] === 'project' && $options['skip-extension'];
        if ($composerOnly && ($options['enable-security'] !== null || $options['service-name'] !== null || $options['otlp-endpoint'] !== null)) {
            throw new InvalidArgumentException('Composer-only installation does not configure startup; pass startup settings in the administrator phase or through environment variables');
        }
        $targets = [];
        foreach (self::resolvePhpBinaries($options['php-bins']) as $binary) {
            $probe = self::probePhp($binary);
            $header = @file_get_contents($binary, false, null, 0, 5);
            if ($header === "\x7fELF\x01") { throw new RuntimeException('32-bit PHP binaries are not supported: ' . $binary); }
            $minor = implode('.', array_slice(explode('.', $probe['version']), 0, 2));
            if (($probe['php-api'] !== (self::PHP_API_BY_VERSION[$minor] ?? null)) || $probe['debug']) { throw new RuntimeException('Unsupported PHP ABI/debug build: ' . $binary); }
            if ($probe['otel-version'] !== null && version_compare($probe['otel-version'], '1.4.2', '<')) { throw new RuntimeException('Loaded opentelemetry engine is older than 1.4.2: ' . $binary); }
            if ($probe['otel-version'] !== null && $probe['otel-distribution'] !== 'Beacon') { throw new RuntimeException('An upstream opentelemetry extension is already loaded; disable it before installing Beacon for ' . $binary); }
            if ($options['skip-extension'] && $probe['otel-distribution'] !== 'Beacon') { throw new RuntimeException('--skip-extension requires an enabled Beacon extension'); }
            $asset = self::selectAsset(PHP_OS_FAMILY, $probe['architecture'] ?? php_uname('m'), $probe['version'], $probe['thread-safe'], self::isMuslLinux());
            $targets[] = ['binary' => $binary, 'probe' => $probe, 'asset' => $asset];
        }
        if ($targets === []) { throw new RuntimeException('No usable PHP targets'); }
        self::println('Beacon PHP ' . self::AGENT_VERSION . ' / ' . $options['mode']);
        $root = self::absolutePath($options['install-dir']);
        foreach ($targets as $target) { self::println('Target ' . $target['binary'] . ($composerOnly ? ' (Composer only)' : ' -> ' . self::iniPath($target['probe'], $options))); }
        if ($options['dry-run']) {
            if (!$composerOnly) { self::println('Would configure startup under ' . $root); }
            self::println($options['mode'] === 'standalone' ? 'No application Composer files will be changed.' : 'Would configure the application Composer artifact repository and install selected packages.');
            return 0;
        }
        $temporary = self::makeTemporaryDirectory();
        $restore = [];
        try {
            $runtime = $options['mode'] === 'standalone' ? self::installRuntime($root, $temporary, $options) : null;
            if ($options['mode'] === 'project' && !$options['skip-composer']) { self::installProject($root, $temporary, $options); }
            if ($composerOnly) { self::println('Composer packages installed. Configure startup in the administrator phase; no PHP INI files were changed.'); return 0; }
            if ($options['mode'] === 'project' && !is_readable(self::absolutePath($options['project']) . '/vendor/autoload.php')) { throw new RuntimeException('Install the application Composer dependencies before configuring project startup'); }
            if ($options['mode'] === 'project') {
                $package = self::absolutePath($options['project']) . '/vendor/beacon-observability/beacon-php/composer.json';
                if (!is_file($package) || (self::readJsonObject($package)['version'] ?? null) !== self::AGENT_VERSION) {
                    throw new RuntimeException('Project startup requires the matching Beacon PHP Composer package; run the application-owner Composer phase first');
                }
            }
            foreach ($targets as $target) {
                $ini = self::iniPath($target['probe'], $options);
                if (isset($restore[$ini])) { continue; }
                $stateFile = $ini . '.beacon.json';
                $oldState = is_file($stateFile) ? self::readState($stateFile) : null;
                $oldIni = is_file($ini) ? (string) file_get_contents($ini) : null;
                if ($oldState !== null && hash('sha256', $oldIni ?? '') !== $oldState['ini_sha256']) { throw new RuntimeException('Managed INI was modified; refusing to overwrite: ' . $ini); }
                $original = $oldState !== null ? ($oldState['original_ini'] === null ? null : base64_decode($oldState['original_ini'], true)) : $oldIni;
                $previous = $oldState['previous_prepend'] ?? $target['probe']['auto-prepend-file'];
                $instance = $root . '/instances/' . substr(hash('sha256', $ini), 0, 24) . '/' . self::AGENT_VERSION;
                $launcher = $instance . '/bootstrap.php';
                $oldSettingsFile = ($oldState !== null ? dirname($oldState['launcher']) : $instance) . '/installation.json';
                $oldSettings = is_file($oldSettingsFile) ? self::readJsonObject($oldSettingsFile) : [];
                $settings = ['runtime_dir' => $runtime, 'project_autoload' => self::absolutePath($options['project']) . '/vendor/autoload.php',
                    'mode' => $options['mode'], 'previous_prepend' => $previous,
                    'security_enabled' => $options['enable-security'] ?? ($oldState['security_enabled'] ?? false),
                    'environment' => array_filter(['OTEL_SERVICE_NAME' => $options['service-name'], 'OTEL_EXPORTER_OTLP_ENDPOINT' => $options['otlp-endpoint']], static fn ($v): bool => $v !== null) + ($oldSettings['environment'] ?? [])];
                if (!is_dir($instance) && !mkdir($instance, 0755, true)) { throw new RuntimeException('Cannot create installation directory: ' . $instance); }
                $restore[$ini] = ['ini' => $oldIni, 'state' => is_file($stateFile) ? file_get_contents($stateFile) : null,
                    'launcher' => is_file($launcher) ? file_get_contents($launcher) : null, 'settings' => is_file($instance . '/installation.json') ? file_get_contents($instance . '/installation.json') : null, 'instance' => $instance];
                self::atomicFile($instance . '/installation.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                if ($runtime !== null) { self::atomicFile($launcher, (string) file_get_contents($runtime . '/bootstrap.php')); }
                else { self::atomicFile($launcher, self::projectBootstrap()); }
                $contents = ($original ?? '') . "\n" . self::INI_MARKER . "\n";
                $ownsExtension = (bool) ($oldState['owns_extension'] ?? false);
                if (!$options['skip-extension'] && ($target['probe']['otel-version'] === null || $ownsExtension)) {
                    $archive = self::fetchArchive($target['asset']['name'], $target['asset']['sha256'], self::EXTENSION_REPOSITORY . '/releases/download/' . self::EXTENSION_TAG, $temporary, $options);
                    $extensionRoot = $temporary . '/native-' . count($restore);
                    self::extractArchive($archive, $extensionRoot);
                    $extension = self::findFile($extensionRoot, $target['asset']['extension-file']);
                    $dir = $options['extension-dir'] ?? $target['probe']['extension-dir'];
                    $destination = $dir . DIRECTORY_SEPARATOR . $target['asset']['installed-file'];
                    if (!is_dir($dir)) { throw new RuntimeException('Extension directory does not exist: ' . $dir); }
                    if (!is_file($destination) || hash_file('sha256', $destination) !== hash_file('sha256', $extension)) { self::atomicFile($destination, (string) file_get_contents($extension), 0755); }
                    $contents .= 'extension=' . self::iniString($destination) . "\n";
                    $ownsExtension = true;
                }
                $contents .= 'auto_prepend_file=' . self::iniString($launcher) . "\n";
                $state = ['schema_version' => 1, 'version' => self::AGENT_VERSION, 'mode' => $options['mode'], 'runtime_dir' => $runtime,
                    'launcher' => $launcher, 'previous_prepend' => $previous, 'security_enabled' => $settings['security_enabled'],
                    'original_ini' => $original === null ? null : base64_encode($original), 'ini_sha256' => hash('sha256', $contents), 'owns_extension' => $ownsExtension];
                self::atomicFile($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", 0600);
                self::atomicFile($ini, $contents);
            }
            if (self::doctor($options) !== 0) { throw new RuntimeException('Installation verification failed; previous configuration has been restored'); }
            self::println('Installed. Restart PHP-FPM or Apache, then run beacon-setup.php doctor.');
            return 0;
        } catch (Throwable $error) {
            foreach ($restore as $ini => $saved) {
                foreach ([$ini => $saved['ini'], $ini . '.beacon.json' => $saved['state'], $saved['instance'] . '/installation.json' => $saved['settings'], $saved['instance'] . '/bootstrap.php' => $saved['launcher']] as $path => $contents) {
                    if ($contents === null) { @unlink($path); } else { self::atomicFile($path, $contents, str_ends_with($path, '.beacon.json') ? 0600 : 0644); }
                }
            }
            throw $error;
        } finally { self::removeDirectory($temporary); }
    }

    private static function installRuntime(string $root, string $temporary, array $options): string
    {
        $name = 'beacon-php-runtime-' . self::AGENT_VERSION . '.tar.gz';
        $archive = self::fetchArchive($name, self::RUNTIME_SHA256, self::AGENT_REPOSITORY . '/releases/download/v' . self::AGENT_VERSION, $temporary, $options);
        $destination = $root . '/versions/' . self::AGENT_VERSION;
        if (is_file($destination . '/runtime.json')) {
            $metadata = self::readJsonObject($destination . '/runtime.json');
            if (($metadata['archive_sha256'] ?? null) !== self::RUNTIME_SHA256) { throw new RuntimeException('Existing runtime version has a different archive checksum'); }
            self::verifyRuntime($destination, $metadata);
            return $destination;
        }
        if (file_exists($destination)) { throw new RuntimeException('Runtime destination is not an existing Beacon installation: ' . $destination); }
        if (!is_dir($root) && !mkdir($root, 0755, true)) { throw new RuntimeException('Cannot create installation root: ' . $root); }
        $extracted = $root . '/.staging-' . bin2hex(random_bytes(8));
        try {
            self::extractArchive($archive, $extracted);
            $runtime = $extracted . '/beacon-php-runtime-' . self::AGENT_VERSION;
            $metadata = self::readJsonObject($runtime . '/runtime.json');
            if (($metadata['version'] ?? null) !== self::AGENT_VERSION || ($metadata['prefix'] ?? null) !== 'BeaconStandalone'
                || !is_file($runtime . '/bootstrap.php') || !is_file($runtime . '/autoload.php') || !is_file($runtime . '/classmap.php')) {
                throw new RuntimeException('Invalid runtime release manifest');
            }
            self::verifyRuntime($runtime, $metadata);
            $metadata['archive_sha256'] = self::RUNTIME_SHA256;
            file_put_contents($runtime . '/runtime.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            if (!is_dir(dirname($destination))) { mkdir(dirname($destination), 0755, true); }
            if (!@rename($runtime, $destination)) { throw new RuntimeException('Cannot activate runtime at ' . $destination); }
            return $destination;
        } finally { self::removeDirectory($extracted); }
    }

    private static function verifyRuntime(string $runtime, array $metadata): void
    {
        if (!is_array($metadata['files'] ?? null) || count($metadata['files']) < 10) { throw new RuntimeException('Missing runtime file inventory'); }
        foreach ($metadata['files'] as $file => $hash) {
            if (!is_string($file) || str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, '\\')
                || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)
                || !is_file($runtime . '/' . $file) || hash_file('sha256', $runtime . '/' . $file) !== $hash) {
                throw new RuntimeException('Runtime file integrity check failed: ' . $file);
            }
        }
    }

    private static function fetchArchive(string $name, string $hash, string $base, string $temporary, array $options): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) { throw new RuntimeException('Use the installer published with the release; this source template has no pinned artifact checksum.'); }
        $path = $options['offline-dir'] !== null ? self::absolutePath($options['offline-dir']) . '/' . $name : $temporary . '/' . $name;
        if ($options['offline-dir'] === null) { self::download($base . '/' . $name, $path); }
        self::verifyFile($path, $hash);
        return $path;
    }

    private static function installProject(string $root, string $temporary, array $options): void
    {
        if (!class_exists(ZipArchive::class)) { throw new RuntimeException('Project mode requires PHP zip for Composer artifact repositories'); }
        $name = 'beacon-php-' . self::AGENT_VERSION . '.zip';
        $archive = self::fetchArchive($name, self::AGENT_SHA256, self::AGENT_REPOSITORY . '/releases/download/v' . self::AGENT_VERSION, $temporary, $options);
        $directory = $root . '/archives';
        if (!is_dir($directory)) { mkdir($directory, 0755, true); }
        if (realpath($archive) !== realpath($directory . '/' . $name)) { copy($archive, $directory . '/' . $name); }
        $composer = self::composerExecutable($options['composer']);
        $prefix = str_ends_with($composer, '.phar') ? [PHP_BINARY, $composer] : [$composer];
        $result = self::runCommand([...$prefix, 'config', '--working-dir=' . self::absolutePath($options['project']), 'repositories.beacon', 'artifact', $directory]);
        if ($result['code'] !== 0) { throw new RuntimeException('Cannot configure Composer artifact repository: ' . $result['stderr']); }
        $packages = self::composerPackages($options);
        self::installComposerPackages(['packages' => $packages, 'command' => self::composerCommand($packages, $options)]);
    }

    private static function readState(string $path): array
    {
        $state = self::readJsonObject($path);
        if (($state['schema_version'] ?? null) !== 1 || !is_string($state['ini_sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/', $state['ini_sha256'])
            || !array_key_exists('original_ini', $state) || !is_string($state['previous_prepend'] ?? null)
            || !is_string($state['launcher'] ?? null) || !in_array($state['mode'] ?? '', ['standalone', 'project'], true)
            || ($state['original_ini'] !== null && (!is_string($state['original_ini']) || base64_decode($state['original_ini'], true) === false))) {
            throw new RuntimeException('Invalid managed installation state: ' . $path);
        }
        return $state;
    }

    private static function atomicFile(string $path, string $contents, int $mode = 0644): void
    {
        if (is_link($path)) { throw new RuntimeException('Refusing to replace a symbolic link: ' . $path); }
        $stat = is_file($path) ? stat($path) : false;
        $temp = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (@file_put_contents($temp, $contents) !== strlen($contents)) { @unlink($temp); throw new RuntimeException('Cannot stage file: ' . $path); }
        chmod($temp, $mode === 0644 && $stat !== false ? $stat['mode'] & 0777 : $mode);
        if ($stat !== false && PHP_OS_FAMILY !== 'Windows') {
            if (fileowner($temp) !== $stat['uid'] && !@chown($temp, $stat['uid'])) { @unlink($temp); throw new RuntimeException('Cannot preserve file owner: ' . $path); }
            if (filegroup($temp) !== $stat['gid'] && !@chgrp($temp, $stat['gid'])) { @unlink($temp); throw new RuntimeException('Cannot preserve file group: ' . $path); }
        }
        if (!@rename($temp, $path)) { @unlink($temp); throw new RuntimeException('Cannot activate file: ' . $path); }
    }

    private static function iniString(string $path): string
    {
        if (strpbrk($path, "\r\n\0\"") !== false) { throw new InvalidArgumentException('INI paths cannot contain quotes or control characters'); }
        return '"' . str_replace('\\', '/', $path) . '"';
    }

    private static function uninstall(array $options): int
    {
        foreach (self::resolvePhpBinaries($options['php-bins']) as $binary) {
            $ini = self::iniPath(self::probePhp($binary), $options);
            $stateFile = $ini . '.beacon.json';
            if (!is_file($stateFile)) { self::println('Not managed by Beacon: ' . $ini); continue; }
            $state = self::readState($stateFile);
            if (!is_file($ini) || hash_file('sha256', $ini) !== $state['ini_sha256']) { throw new RuntimeException('Managed INI was modified; preserve your edits before uninstalling: ' . $ini); }
            if ($options['dry-run']) { self::println('Would restore original INI: ' . $ini); continue; }
            if ($state['original_ini'] === null) { unlink($ini); }
            else { self::atomicFile($ini, base64_decode($state['original_ini'], true)); }
            unlink($stateFile);
            self::println('Restored original startup configuration: ' . $ini);
        }
        self::println('Versioned runtime and extension files retained. Restart PHP services to finish uninstalling.');
        return 0;
    }

    private static function doctor(array $options): int
    {
        $failed = false;
        foreach (self::resolvePhpBinaries($options['php-bins']) as $binary) {
            $probe = self::probePhp($binary);
            $stateFile = self::iniPath($probe, $options) . '.beacon.json';
            if (!is_file($stateFile)) { self::println('[FAIL] No managed installation for ' . $binary); $failed = true; continue; }
            $state = self::readState($stateFile);
            if ($state['mode'] === 'standalone') { self::verifyRuntime($state['runtime_dir'], self::readJsonObject($state['runtime_dir'] . '/runtime.json')); }
            self::println('Beacon PHP ' . $state['version'] . ' / ' . $state['mode'] . ' / ' . $binary);
            if ($probe['otel-distribution'] !== 'Beacon' || realpath($probe['auto-prepend-file']) !== realpath($state['launcher'])) { self::println('[FAIL] PHP has not loaded the installed extension/bootstrap'); $failed = true; continue; }
            if ($state['mode'] === 'standalone' && str_contains(strtolower($probe['server-api']), 'command line')) {
                putenv('BEACON_SETUP_DIAGNOSTICS=1');
                $result = self::runCommand([$binary, $state['runtime_dir'] . '/doctor.php']);
                putenv('BEACON_SETUP_DIAGNOSTICS');
                fwrite(STDOUT, $result['stdout']); fwrite(STDERR, $result['stderr']);
                $failed = $failed || $result['code'] !== 0;
            } else { self::println('[OK] Startup configuration'); }
        }
        return $failed ? 1 : 0;
    }

    private static function projectBootstrap(): string
    {
        return <<<'BOOT'
<?php
if (in_array(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), ['beacon-setup.php', 'composer', 'composer.phar'], true)) { return; }
$beacon_settings = json_decode(file_get_contents(__DIR__ . '/installation.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($beacon_settings['environment'] as $beaconKey => $beaconValue) { if (getenv($beaconKey) === false) { putenv($beaconKey . '=' . $beaconValue); } }
if (getenv('BEACON_SECURITY_ENABLED') === false && $beacon_settings['security_enabled']) { putenv('BEACON_SECURITY_ENABLED=true'); }
if (getenv('OTEL_PHP_AUTOLOAD_ENABLED') === false) { putenv('OTEL_PHP_AUTOLOAD_ENABLED=true'); }
require_once $beacon_settings['project_autoload'];
if (!empty($beacon_settings['previous_prepend']) && getenv('BEACON_SETUP_DIAGNOSTICS') !== '1') { require $beacon_settings['previous_prepend']; }
unset($beacon_settings, $beaconKey, $beaconValue);
BOOT;
    }

    private static function installComposerPackages(array $composerPlan): void
    {
        self::println('Installing Agent and OpenTelemetry components: ' . implode(', ', $composerPlan['packages']));
        $result = self::runCommand($composerPlan['command']);
        fwrite(STDOUT, $result['stdout']);
        fwrite(STDERR, $result['stderr']);
        if ($result['code'] !== 0) {
            throw new RuntimeException(sprintf('Composer installation failed with exit code %d', $result['code']));
        }
    }

    /** @param array<string, mixed> $options @return list<string> */
    private static function composerPackages(array $options): array
    {
        $project = self::absolutePath($options['project']);
        $aliases = [];
        if ($options['components'] === 'auto') {
            $aliases = self::detectComponentAliases($project);
            foreach (self::EXTENSION_COMPONENT_DETECTION as $extension => $alias) {
                if (extension_loaded($extension) && !in_array($alias, $aliases, true)) {
                    $aliases[] = $alias;
                }
            }
            $aliases = array_values(array_filter(
                array_keys(self::COMPONENT_PACKAGES),
                static fn (string $alias): bool => in_array($alias, $aliases, true),
            ));
        } elseif ($options['components'] !== 'none') {
            $aliases = array_values(array_filter(array_map('trim', explode(',', $options['components']))));
        }

        $packages = [sprintf('beacon-observability/beacon-php:%s', self::AGENT_VERSION)];
        foreach ($aliases as $alias) {
            if (!isset(self::COMPONENT_PACKAGES[$alias])) {
                throw new RuntimeException(sprintf(
                    'Unknown component alias %s; available aliases: %s',
                    $alias,
                    implode(', ', array_keys(self::COMPONENT_PACKAGES)),
                ));
            }
            $packages[] = self::COMPONENT_PACKAGES[$alias];
        }
        if ($options['components'] === 'auto') {
            self::println($aliases === []
                ? 'No optional instrumentation components were detected.'
                : 'Auto-detected components: ' . implode(', ', $aliases));
        }

        return $packages;
    }

    /** @param list<string> $packages @param array<string, mixed> $options @return list<string> */
    private static function composerCommand(array $packages, array $options): array
    {
        $composer = self::composerExecutable($options['composer']);
        $prefix = [$composer];
        if (str_ends_with(strtolower($composer), '.phar')) {
            $prefix = [PHP_BINARY, $composer];
        } elseif (preg_match('/\.(?:bat|cmd)$/i', $composer)) {
            $phar = dirname($composer) . DIRECTORY_SEPARATOR . 'composer.phar';
            if (!is_file($phar)) {
                throw new RuntimeException('A Composer batch wrapper was found without composer.phar; pass --composer=/path/to/composer.phar');
            }
            $prefix = [PHP_BINARY, $phar];
        }
        return array_merge(
            $prefix,
            ['require'],
            $packages,
            ['--no-interaction', '--no-progress', '--working-dir=' . self::absolutePath($options['project'])],
        );
    }

    /** @param list<string> $requested @return list<string> */
    private static function resolvePhpBinaries(array $requested): array
    {
        $all = in_array('all', array_map('strtolower', $requested), true);
        $candidates = $all ? self::discoverPhpBinaries() : $requested;
        $resolved = [];
        foreach ($candidates as $candidate) {
            try {
                $path = self::findExecutable($candidate);
            } catch (RuntimeException) {
                if (!$all) {
                    throw new RuntimeException(sprintf('PHP binary is not executable: %s', $candidate));
                }
                continue;
            }
            $canonical = realpath($path) ?: $path;
            $resolved[$canonical] = $canonical;
        }
        return array_values($resolved);
    }

    /** @return list<string> */
    private static function discoverPhpBinaries(): array
    {
        $candidates = [PHP_BINARY];
        $names = ['php', 'php8.2', 'php8.3', 'php8.4', 'php-fpm', 'php-fpm8.2', 'php-fpm8.3', 'php-fpm8.4'];
        $directories = array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH')));
        if (PHP_OS_FAMILY !== 'Windows') {
            $directories = array_merge($directories, ['/usr/bin', '/usr/sbin', '/usr/local/bin', '/usr/local/sbin', '/opt/homebrew/bin', '/opt/homebrew/sbin']);
        }
        foreach (array_unique($directories) as $directory) {
            foreach ($names as $name) {
                $suffixes = PHP_OS_FAMILY === 'Windows' ? ['.exe', '.bat', '.cmd', ''] : [''];
                foreach ($suffixes as $suffix) {
                    $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . $suffix;
                    if (is_file($candidate) && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
                        $candidates[] = $candidate;
                    }
                }
            }
        }
        foreach (['/opt/php/*/bin/php', '/opt/php/*/sbin/php-fpm', '/usr/local/opt/php*/bin/php'] as $pattern) {
            foreach (glob($pattern) ?: [] as $candidate) {
                $candidates[] = $candidate;
            }
        }
        return array_values(array_unique($candidates));
    }

    /**
     * @return array{version: string, php-api: string, extension-build: string, thread-safe: bool, debug: bool, extension-dir: string, scan-dir: ?string, loaded-ini: ?string, server-api: string}
     */
    private static function probePhp(string $binary): array
    {
        $result = self::runCommand([$binary, '-i']);
        if ($result['code'] !== 0) {
            throw new RuntimeException(sprintf('Unable to inspect %s: %s', $binary, trim($result['stderr'])));
        }
        $info = $result['stdout'];
        $read = static function (string $label) use ($info): ?string {
            if (!preg_match('/^' . preg_quote($label, '/') . '\s*=>\s*([^\r\n]+)$/mi', $info, $matches)) {
                return null;
            }
            $parts = array_map('trim', explode('=>', trim($matches[1])));
            return $parts[0] === '' || strtolower($parts[0]) === '(none)' ? null : $parts[0];
        };

        $otel = explode("\nopentelemetry\n", str_replace("\r\n", "\n", $info), 2)[1] ?? '';
        $otelValue = static function (string $name) use ($otel): ?string {
            return preg_match('/^' . preg_quote($name, '/') . '\s*=>\s*([^\r\n]+)/m', $otel, $matches) ? trim(explode('=>', $matches[1])[0]) : null;
        };
        $version = $read('PHP Version');
        $phpApi = $read('PHP API');
        $extensionBuild = $read('PHP Extension Build');
        $extensionDirectory = $read('extension_dir');
        if ($version === null || $phpApi === null || $extensionBuild === null || $extensionDirectory === null) {
            throw new RuntimeException(sprintf('Incomplete phpinfo output from %s', $binary));
        }
        $threadSafety = strtolower((string) $read('Thread Safety'));
        $debugBuild = strtolower((string) $read('Debug Build'));

        return [
            'version' => $version,
            'php-api' => $phpApi,
            'extension-build' => $extensionBuild,
            'thread-safe' => in_array($threadSafety, ['enabled', 'yes', 'on', '1'], true),
            'debug' => in_array($debugBuild, ['enabled', 'yes', 'on', '1'], true),
            'extension-dir' => $extensionDirectory,
            'scan-dir' => $read('Scan this dir for additional .ini files'),
            'loaded-ini' => $read('Loaded Configuration File'),
            'otel-version' => $otelValue('extension version'),
            'otel-distribution' => $otelValue('extension distribution'),
            'auto-prepend-file' => ($read('auto_prepend_file') === 'no value' ? '' : ($read('auto_prepend_file') ?? '')),
            'architecture' => $read('Architecture'),
            'server-api' => $read('Server API') ?? 'unknown',
        ];
    }

    /** @param array<string, mixed> $probe @param array<string, mixed> $options */
    private static function iniPath(array $probe, array $options): string
    {
        if (is_string($options['ini'])) {
            return self::absolutePath($options['ini']);
        }
        if (!is_string($probe['scan-dir']) || $probe['scan-dir'] === '') {
            throw new RuntimeException('PHP has no additional INI scan directory; provide --ini=/path/to/99-beacon-opentelemetry.ini');
        }
        $directories = array_values(array_filter(explode(PATH_SEPARATOR, $probe['scan-dir'])));
        if ($directories === []) { throw new RuntimeException('No explicit INI scan directory is available'); }
        return rtrim(end($directories), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::INI_FILENAME;
    }

    private static function download(string $url, string $destination): void
    {
        $context = stream_context_create([
            'http' => [
                'follow_location' => 1,
                'max_redirects' => 5,
                'timeout' => 60,
                'user_agent' => 'Beacon-PHP-Setup/' . self::AGENT_VERSION,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $source = @fopen($url, 'rb', false, $context);
        if (is_resource($source)) {
            $target = @fopen($destination, 'wb');
            if (is_resource($target)) {
                $copied = stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
                if ($copied !== false) {
                    return;
                }
            } else {
                fclose($source);
            }
        }

        try {
            $curl = self::findExecutable('curl');
        } catch (RuntimeException) {
            throw new RuntimeException(sprintf('Unable to download %s; enable allow_url_fopen or install curl', $url));
        }
        $result = self::runCommand([$curl, '--fail', '--location', '--retry', '3', '--output', $destination, $url]);
        if ($result['code'] !== 0) {
            throw new RuntimeException(sprintf('Unable to download %s: %s', $url, trim($result['stderr'])));
        }
    }

    private static function verifyFile(string $path, string $expectedHash): void
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Archive does not exist: %s', $path));
        }
        $actualHash = hash_file('sha256', $path);
        if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException(sprintf('SHA-256 mismatch for %s (expected %s, got %s)', basename($path), $expectedHash, (string) $actualHash));
        }
        self::println(sprintf('Verified SHA-256 for %s', basename($path)));
    }

    private static function extractArchive(string $archive, string $destination): void
    {
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException(sprintf('Unable to create extraction directory: %s', $destination));
        }
        try {
            $tar = self::findExecutable('tar');
            $result = self::runCommand([$tar, '-xf', $archive, '-C', $destination]);
            if ($result['code'] === 0) {
                return;
            }
        } catch (RuntimeException) {
            // Use PHP archive support below.
        }

        if (str_ends_with(strtolower($archive), '.zip')) {
            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('Unable to extract ZIP archive; install tar or enable ext-zip');
            }
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true || !$zip->extractTo($destination)) {
                throw new RuntimeException(sprintf('Unable to extract %s', $archive));
            }
            $zip->close();
            return;
        }

        try {
            $phar = new PharData($archive);
            $phar->extractTo($destination, null, true);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Unable to extract %s: %s', $archive, $exception->getMessage()), 0, $exception);
        }
    }

    private static function findFile(string $directory, string $filename): string
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        throw new RuntimeException(sprintf('%s was not found in the release archive', $filename));
    }

    private static function makeTemporaryDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'beacon-php-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) {
            throw new RuntimeException(sprintf('Unable to create temporary directory: %s', $directory));
        }
        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
        @rmdir($directory);
    }

    /** @param list<string> $command @return array{code: int, stdout: string, stderr: string} */
    private static function runCommand(array $command): array
    {
        // File-backed capture avoids a deadlock when Composer fills stderr while stdout is read.
        $stdout = tmpfile(); $stderr = tmpfile();
        if ($stdout === false || $stderr === false) { throw new RuntimeException('Cannot create command capture files'); }
        try {
            $process = proc_open($command,
                [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => $stdout, 2 => $stderr],
                $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) { throw new RuntimeException('Unable to start command: ' . self::renderCommand($command)); }
            $code = proc_close($process);
            rewind($stdout); rewind($stderr);
            return ['code' => $code, 'stdout' => (string) stream_get_contents($stdout), 'stderr' => (string) stream_get_contents($stderr)];
        } finally { fclose($stdout); fclose($stderr); }
    }

    /** @param list<string> $command */
    private static function renderCommand(array $command): string
    {
        return implode(' ', array_map(static function (string $argument): string {
            if ($argument !== '' && preg_match('/^[a-zA-Z0-9_@%+=:,\.\/\\-]+$/', $argument)) {
                return $argument;
            }
            return "'" . str_replace("'", "'\\''", $argument) . "'";
        }, $command));
    }

    private static function composerExecutable(string $command): string
    {
        if (str_ends_with(strtolower($command), '.phar') && is_readable($command)) { return self::absolutePath($command); }
        return self::findExecutable($command);
    }

    private static function findExecutable(string $command): string
    {
        $hasDirectory = str_contains($command, '/') || str_contains($command, '\\');
        $extensions = PHP_OS_FAMILY === 'Windows' ? ['', '.exe', '.bat', '.cmd'] : [''];
        $candidates = $hasDirectory ? [$command] : array_map(
            static fn (string $directory): string => rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command,
            array_filter(explode(PATH_SEPARATOR, (string) getenv('PATH'))),
        );
        foreach ($candidates as $candidate) {
            foreach ($extensions as $extension) {
                $path = $candidate . $extension;
                if (is_file($path) && (PHP_OS_FAMILY === 'Windows' || is_executable($path))) {
                    return $path;
                }
            }
        }
        throw new RuntimeException(sprintf('Executable not found: %s', $command));
    }

    /** @return array<string, mixed> */
    private static function readJsonObject(string $path): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('Invalid JSON in %s: %s', $path, $exception->getMessage()), 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Expected a JSON object in %s', $path));
        }
        return $decoded;
    }

    private static function absolutePath(string $path): string
    {
        $resolved = realpath($path);
        if ($resolved !== false) {
            return $resolved;
        }
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return $path;
        }
        return (getcwd() ?: '.') . DIRECTORY_SEPARATOR . $path;
    }

    private static function normalizeArchitecture(string $architecture): string
    {
        return match (strtolower(trim($architecture))) {
            'x86_64', 'amd64', 'x64' => 'x86_64',
            'aarch64', 'arm64' => 'arm64',
            default => throw new RuntimeException(sprintf('Unsupported CPU architecture: %s', $architecture)),
        };
    }

    private static function linuxPlatform(string $architecture, bool $threadSafe, bool $musl): string
    {
        if ($musl) {
            throw new RuntimeException('Prebuilt Beacon Linux extensions require glibc; use the PECL-compatible source archive on musl/Alpine');
        }
        if ($threadSafe) {
            throw new RuntimeException('Prebuilt Beacon Linux extensions currently support NTS PHP only');
        }
        return 'linux-' . $architecture;
    }

    private static function macPlatform(string $architecture, bool $threadSafe): string
    {
        if ($architecture !== 'arm64' || $threadSafe) {
            throw new RuntimeException('Prebuilt Beacon macOS extensions currently support arm64 NTS PHP only');
        }
        return 'macos-arm64';
    }

    private static function windowsPlatform(string $architecture): string
    {
        if ($architecture !== 'x86_64') {
            throw new RuntimeException('Prebuilt Beacon Windows extensions currently support x86_64 PHP only');
        }
        return 'vs17-x86_64';
    }

    private static function isMuslLinux(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }
        if (is_file('/etc/alpine-release')) {
            return true;
        }
        foreach (glob('/lib/ld-musl-*.so.1') ?: [] as $loader) {
            if (is_file($loader)) {
                return true;
            }
        }
        return false;
    }

    private static function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || (!isset($parts['user']) && !isset($parts['pass']) && !(isset($parts['query']) || isset($parts['fragment'])))) {
            return $url;
        }
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        return $scheme . $host . $port . $path . (isset($parts['query']) ? '?[redacted]' : '');
    }

    private static function println(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private static function printHelp(): void
    {
        echo <<<'HELP'
Beacon PHP setup
Usage: php beacon-setup.php [install|upgrade|uninstall|doctor] [options]

  --php-bin=all|/path/to/php   Target PHP binaries (repeatable)
  --install-dir=/opt/beacon/php
  --enable-security          Enable opt-in Security and runtime SBOM
  --disable-security         Disable Security on reinstall/upgrade
  --offline-dir=/path        Directory containing the exact released archives
  --dry-run                  Show the plan without modifying files
  --ini=/path/99-beacon.ini   Explicit INI path (one PHP target)
  --extension-dir=/path      Explicit extension directory (one PHP target)
  --skip-extension           Project mode: Composer only; requires enabled Beacon extension

Advanced Composer integration:
  --mode=project --project=/path/to/app [--components=auto|pdo,curl,...]
  --composer=/path/to/composer.phar
  --skip-composer            Configure an already-installed project

Standalone mode is the default. It requires no Composer on the target and does
not modify application Composer files. Configure transport and identity using
standard OTEL_* variables. Restart PHP-FPM/Apache after installation changes.
HELP;
        echo PHP_EOL;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(BeaconSetup::main($argv));
}
