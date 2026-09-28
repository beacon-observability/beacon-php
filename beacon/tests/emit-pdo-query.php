<?php

declare(strict_types=1);

$autoloadPath = $argv[1] ?? getcwd() . '/vendor/autoload.php';
require $autoloadPath;

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE beacon_integration_probe (id INTEGER PRIMARY KEY, value TEXT)');
$statement = $database->prepare('INSERT INTO beacon_integration_probe (value) VALUES (:value)');
$statement->execute(['value' => 'ok']);
$result = $database->query('SELECT value FROM beacon_integration_probe WHERE id = 1')->fetchColumn();

if ($result !== 'ok') {
    fwrite(STDERR, "PDO integration probe returned an unexpected value.\n");
    exit(1);
}

fwrite(STDOUT, "Executed Beacon PDO auto-instrumentation probe.\n");
