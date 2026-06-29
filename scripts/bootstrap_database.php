<?php

declare(strict_types=1);

use App\Core\Database;

require_once __DIR__ . '/../bootstrap/env.php';

$pdo = Database::getConnection();
$databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$tableCount = $pdo->prepare(
    'SELECT COUNT(*)
     FROM information_schema.tables
     WHERE table_schema = :database_name'
);
$tableCount->execute(['database_name' => $databaseName]);

if ((int)$tableCount->fetchColumn() > 0) {
    throw new RuntimeException('Database bootstrap requires an empty database. Use composer migrate for an existing database.');
}

$schemaPath = dirname(__DIR__) . '/schema.sql';
$schema = file_get_contents($schemaPath);
if ($schema === false || trim($schema) === '') {
    throw new RuntimeException('Unable to read schema.sql');
}

$pdo->exec($schema);
$pdo->exec(
    'CREATE TABLE migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )'
);

$insertMigration = $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)');
$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $insertMigration->execute([basename($file)]);
}

echo sprintf(
    "Database bootstrapped from schema.sql; %d migrations marked as applied.\n",
    count($migrationFiles)
);
