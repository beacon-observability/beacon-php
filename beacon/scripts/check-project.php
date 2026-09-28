<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

$lockPath = $root . '/beacon/upstream.lock.json';
$lock = json_decode((string) file_get_contents($lockPath), true, flags: JSON_THROW_ON_ERROR);
if (($lock['schemaVersion'] ?? null) !== 1) {
    $fail('upstream.lock.json schemaVersion must be 1');
}

foreach (['import', 'upstream'] as $section) {
    $commit = $lock[$section]['commit'] ?? '';
    if (!is_string($commit) || !preg_match('/^[0-9a-f]{40}$/', $commit)) {
        $fail(sprintf('%s.commit must be a full lowercase Git SHA-1', $section));
    }
}

foreach (['commit', 'upstreamReleaseCommit'] as $key) {
    $extensionCommit = $lock['instrumentationExtension'][$key] ?? '';
    if (!is_string($extensionCommit) || !preg_match('/^[0-9a-f]{40}$/', $extensionCommit)) {
        $fail(sprintf('instrumentationExtension.%s must be a full lowercase Git SHA-1', $key));
    }
}
if (($lock['instrumentationExtension']['distribution'] ?? null) !== 'Beacon') {
    $fail('instrumentationExtension.distribution must be Beacon');
}
$extensionBeaconVersion = $lock['instrumentationExtension']['beaconVersion'] ?? '';
if (!is_string($extensionBeaconVersion) || !preg_match('/^\d+\.\d+\.\d+$/', $extensionBeaconVersion)) {
    $fail('instrumentationExtension.beaconVersion must use X.Y.Z');
}
if (($lock['instrumentationExtension']['tag'] ?? null) !== 'v' . $extensionBeaconVersion) {
    $fail('instrumentationExtension.tag must match its Beacon version');
}

$properties = parse_ini_file($root . '/beacon/version.properties');
$version = $properties['version'] ?? '';
if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+(?:-dev|-rc\.\d+)?$/', $version)) {
    $fail('Beacon version must use X.Y.Z, X.Y.Z-dev, or X.Y.Z-rc.N');
}

$versionSource = (string) file_get_contents($root . '/beacon-package/src/Version.php');
if (!str_contains($versionSource, "public const VERSION = '" . $version . "';")) {
    $fail('beacon-package/src/Version.php does not match beacon/version.properties');
}

$composer = json_decode(
    (string) file_get_contents($root . '/beacon-package/composer.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
if (($composer['name'] ?? null) !== 'beacon-observability/beacon-php') {
    $fail('Beacon Composer package name is incorrect');
}
if (($composer['version'] ?? null) !== $version) {
    $fail('Beacon Composer package version must match beacon/version.properties');
}
$minimumExtensionVersion = $lock['instrumentationExtension']['minimumVersion'] ?? '';
if (($composer['require']['ext-opentelemetry'] ?? null) !== '>=' . $minimumExtensionVersion) {
    $fail('Beacon Composer package extension requirement must match the locked minimum version');
}

$workflowFiles = array_merge(
    glob($root . '/.github/workflows/*.yml') ?: [],
    glob($root . '/.github/workflows/*.yaml') ?: [],
);
sort($workflowFiles);
$expectedWorkflowFiles = [
    $root . '/.github/workflows/beacon-ci.yml',
    $root . '/.github/workflows/beacon-release.yml',
];
if ($workflowFiles !== $expectedWorkflowFiles) {
    $fail('Beacon PHP must expose the daily CI and release workflows only');
}
$workflowSource = is_file($expectedWorkflowFiles[0])
    ? (string) file_get_contents($expectedWorkflowFiles[0])
    : '';
$beaconExtensionCommit = $lock['instrumentationExtension']['commit'] ?? '';
if (!str_contains($workflowSource, 'ref: ' . $beaconExtensionCommit)) {
    $fail('Beacon CI extension ref must match upstream.lock.json');
}

$importCommit = $lock['import']['commit'] ?? '';
if (is_string($importCommit) && preg_match('/^[0-9a-f]{40}$/', $importCommit)) {
    exec(
        sprintf(
            'git -C %s merge-base --is-ancestor %s HEAD 2>&1',
            escapeshellarg($root),
            escapeshellarg($importCommit),
        ),
        $output,
        $status,
    );
    if ($status !== 0) {
        $fail('Recorded import commit is not an ancestor of HEAD');
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "Error: {$failure}\n");
    }
    exit(1);
}

printf(
    "Beacon PHP project metadata is valid (version=%s, upstream=%s, ext-opentelemetry=%s).\n",
    $version,
    $lock['upstream']['commit'],
    $lock['instrumentationExtension']['minimumVersion'],
);
