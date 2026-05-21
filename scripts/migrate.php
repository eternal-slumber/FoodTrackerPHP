<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/env.php';

$host = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )'
);

$migrationFiles = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $name = basename($file);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
    $stmt->execute([$name]);

    if ((int)$stmt->fetchColumn() > 0) {
        continue;
    }

    try {
        $pdo->exec((string)file_get_contents($file));
        $insert = $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)');
        $insert->execute([$name]);
        echo "Migrated: {$name}\n";
    } catch (Throwable $e) {
        throw $e;
    }
}

echo "Migrations complete.\n";
