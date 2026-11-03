<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$pdo = new PDO('sqlite::memory:');
// Deliberately unsafe fixture: query text is observed but never exported by Security.
$pdo->query("SELECT '" . ($_GET['q'] ?? '') . "'");
header('Content-Type: text/plain');
echo "ok\n";
