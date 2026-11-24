<?php

declare(strict_types=1);

use Beacon\PHP\Security\Contract;
use Beacon\PHP\Security\Siem;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$root = __DIR__ . '/spec';
$vectors = json_decode(file_get_contents($root . '/test-vectors/siem-v1.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($vectors['vectors'] as $vector) {
    $raw = file_get_contents($root . '/' . $vector['input']);
    $actual = Siem::normalize($raw, $vector['resource'], $vector['context']);
    $expected = json_decode(file_get_contents($root . '/' . $vector['expected']), true, flags: JSON_THROW_ON_ERROR);
    if ($actual != $expected || $actual['message'] !== $raw) { throw new RuntimeException('SIEM vector: ' . $vector['name']); }
}
$raw = file_get_contents($root . '/examples/finding-v2.json');
if (Siem::normalize($raw, ['tenant.id' => '0'])['tenant_id'] !== '0'
    || Siem::normalize($raw, ['tenant.id' => 'other'], ['tenant_id' => '0'])['tenant_id'] !== '0') {
    throw new RuntimeException('Nonempty zero tenant identity must be preserved');
}
echo "SIEM reference vectors and exact evidence preservation passed.\n";
