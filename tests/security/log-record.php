<?php

declare(strict_types=1);

use Beacon\PHP\Security\LogRecord;
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$root = __DIR__ . '/spec/examples';
foreach (glob($root . '/log-records/*.json') as $path) {
    $name = basename($path);
    $inputName = match ($name) { 'finding.json' => 'finding-v2.json', 'finding-occurrence.json' => 'finding-occurrence-v2.json', 'sbom-inventory.json' => 'sbom-inventory-v2.json', default => 'siem/inputs/' . $name };
    $input = json_decode(file_get_contents($root . '/' . $inputName), true, flags: JSON_THROW_ON_ERROR);
    $expected = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $actual = LogRecord::bind($input);
    if ($actual['time_unix_nano'] !== $expected['time_unix_nano'] || $actual['attributes'] != array_intersect_key($expected['attributes'], $actual['attributes'])
        || json_decode($actual['body'], true) != json_decode($expected['body'], true) || str_contains($actual['body'], "\n")
        || $actual !== LogRecord::bind($input)) { throw new RuntimeException('Log binding: ' . $name); }
}
$input = json_decode(file_get_contents($root . '/finding-v2.json'), true, flags: JSON_THROW_ON_ERROR);
$input['observed_at'] = '2026-10-02T00:00:00.123456789Z';
if (LogRecord::bind($input)['time_unix_nano'] !== '1790899200123456789') { throw new RuntimeException('Nanosecond precision'); }
foreach (['bad', '2026-02-30T00:00:00Z', '1970-01-01T00:00:00Z', '1969-12-31T23:59:59Z', '2263-01-01T00:00:00Z'] as $time) {
    $input['observed_at'] = $time;
    try { LogRecord::bind($input); } catch (Throwable) { continue; }
    throw new RuntimeException('Invalid event time was accepted');
}
echo "Shared log binding vectors, precision and invalid times passed.\n";
