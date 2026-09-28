<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

$lockPath = $root . '/beacon/compatibility.lock.json';
$lock = json_decode((string) file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
if (($lock['schemaVersion'] ?? null) !== 1) {
    $fail('compatibility.lock.json schemaVersion must be 1');
}
if (($lock['componentSource']['policy'] ?? null) !== 'official-composer-packages') {
    $fail('Component source policy must use official Composer packages');
}
if (($lock['componentSource']['vendoredContribSource'] ?? null) !== false) {
    $fail('Vendored Contrib source must be disabled');
}

$extension = $lock['instrumentationExtension'] ?? [];
foreach (['commit', 'upstreamReleaseCommit'] as $key) {
    $commit = $extension[$key] ?? '';
    if (!is_string($commit) || !preg_match('/^[0-9a-f]{40}$/', $commit)) {
        $fail(sprintf('instrumentationExtension.%s must be a full lowercase Git SHA-1', $key));
    }
}
if (($extension['distribution'] ?? null) !== 'Beacon') {
    $fail('instrumentationExtension.distribution must be Beacon');
}
$extensionBeaconVersion = $extension['beaconVersion'] ?? '';
if (!is_string($extensionBeaconVersion) || !preg_match('/^\d+\.\d+\.\d+$/', $extensionBeaconVersion)) {
    $fail('instrumentationExtension.beaconVersion must use X.Y.Z');
}
if (($extension['tag'] ?? null) !== 'v' . $extensionBeaconVersion) {
    $fail('instrumentationExtension.tag must match its Beacon version');
}

$properties = parse_ini_file($root . '/beacon/version.properties');
$version = $properties['version'] ?? '';
if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+(?:-dev|-rc\.\d+)?$/', $version)) {
    $fail('Beacon version must use X.Y.Z, X.Y.Z-dev, or X.Y.Z-rc.N');
}

$versionSource = (string) file_get_contents($root . '/src/Version.php');
if (!str_contains($versionSource, "public const VERSION = '" . $version . "';")) {
    $fail('src/Version.php does not match beacon/version.properties');
}

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
if (($composer['name'] ?? null) !== 'beacon-observability/beacon-php') {
    $fail('Composer package name is incorrect');
}
if (($composer['version'] ?? null) !== $version) {
    $fail('Composer package version must match beacon/version.properties');
}
$minimumExtensionVersion = $extension['minimumUpstreamExtensionVersion'] ?? '';
if (($composer['require']['ext-opentelemetry'] ?? null) !== '>=' . $minimumExtensionVersion) {
    $fail('Extension requirement must match the compatibility lock');
}

$manifestPath = $root . '/' . ($lock['componentSource']['manifest'] ?? '');
$components = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($components) || $components === []) {
    $fail('Component manifest must be a non-empty JSON object');
} else {
    foreach ($components as $alias => $package) {
        if (!is_string($alias) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $alias)) {
            $fail(sprintf('Invalid component alias: %s', (string) $alias));
        }
        if (!is_string($package) || !preg_match('#^open-telemetry/opentelemetry-auto-[a-z0-9-]+$#', $package)) {
            $fail(sprintf('Component %s must reference an official auto-instrumentation package', (string) $alias));
        }
    }
    if (count($components) !== count(array_unique($components))) {
        $fail('Component package names must be unique');
    }
}

foreach (['beacon-package', 'examples', 'docker', 'files', 'src/Instrumentation'] as $legacyPath) {
    if (file_exists($root . '/' . $legacyPath)) {
        $fail(sprintf('Legacy downstream path must not exist: %s', $legacyPath));
    }
}

$workflowFiles = array_merge(glob($root . '/.github/workflows/*.yml') ?: [], glob($root . '/.github/workflows/*.yaml') ?: []);
sort($workflowFiles);
$expectedWorkflowFiles = [$root . '/.github/workflows/beacon-ci.yml', $root . '/.github/workflows/beacon-release.yml'];
if ($workflowFiles !== $expectedWorkflowFiles) {
    $fail('Beacon PHP must expose the daily CI and release workflows only');
}
$workflowSource = is_file($expectedWorkflowFiles[0]) ? (string) file_get_contents($expectedWorkflowFiles[0]) : '';
if (!str_contains($workflowSource, 'ref: ' . ($extension['commit'] ?? ''))) {
    $fail('Beacon CI extension ref must match compatibility.lock.json');
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "Error: {$failure}\n");
    }
    exit(1);
}

printf(
    "Beacon PHP project metadata is valid (version=%s, components=%d, ext-opentelemetry=%s).\n",
    $version,
    count($components),
    $minimumExtensionVersion,
);
