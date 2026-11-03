<?php

declare(strict_types=1);

if (class_exists('OpenTelemetry\\API\\Globals', false)) { throw new RuntimeException('Application SDK namespace polluted'); }
$pdo = new PDO('sqlite::memory:');
if ($pdo->query('SELECT 42')->fetchColumn() !== 42) { throw new RuntimeException('PDO result changed'); }
header('Content-Type: text/plain');
echo "SERVER_OK\n";
