<?php

declare(strict_types=1);

if (($application_prepend_value ?? null) !== 'preserved' || ($GLOBALS['application_prepend_calls'] ?? 0) !== 1) {
    throw new RuntimeException('Existing auto_prepend_file lost its globals or executed more than once');
}
if (class_exists('OpenTelemetry\\API\\Globals', false) || interface_exists('Psr\\Log\\LoggerInterface', false)) {
    throw new RuntimeException('Standalone dependencies leaked into the application namespace');
}
require __DIR__ . '/vendor/autoload.php';
if (\OpenTelemetry\API\Globals::APPLICATION_VERSION !== 'old-application-sdk') { throw new RuntimeException('Application SDK was replaced'); }
$_GET = ['q' => 'PRIVATE_STANDALONE_VALUE'];
$pdo = new PDO('sqlite::memory:');
if ($pdo->query("SELECT 'PRIVATE_STANDALONE_VALUE'")->fetchColumn() !== 'PRIVATE_STANDALONE_VALUE') { throw new RuntimeException('Application result changed'); }
$thrown = false;
try { $pdo->query('SELECT invalid_column'); } catch (PDOException) { $thrown = true; }
if (!$thrown) { throw new RuntimeException('Application exception was suppressed'); }
if (getenv('OTEL_PHP_AUTOLOAD_ENABLED') !== false) { throw new RuntimeException('Agent changed application SDK auto-configuration'); }
echo "APPLICATION_OK\n";
