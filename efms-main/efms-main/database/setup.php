<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use Dotenv\Dotenv;

if (is_file(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->load();
}

Config::load(__DIR__ . '/../config');
$dbConfig = Config::get('database');

$driver = $dbConfig['driver'] ?? 'sqlite';

if ($driver === 'sqlite') {
    $dbPath = $dbConfig['database'] ?? (__DIR__ . '/database.sqlite');
    if ($dbPath !== ':memory:' && !preg_match('#^([a-zA-Z]:|[\\\\/])#', $dbPath)) {
        $dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($dbPath, '/\\');
    }

    $dbDir = dirname($dbPath);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    echo "Connecting to SQLite database: {$dbPath}" . PHP_EOL;
    $pdo = new PDO("sqlite:{$dbPath}", null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} else {
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=%s',
        $driver,
        $dbConfig['host'] ?? '127.0.0.1',
        $dbConfig['port'] ?? '3306',
        $dbConfig['database'] ?? '',
        $dbConfig['charset'] ?? 'utf8mb4'
    );
    echo "Connecting to {$driver} database..." . PHP_EOL;
    $pdo = new PDO($dsn, $dbConfig['username'] ?? '', $dbConfig['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

$schemaFile = __DIR__ . '/schema.sql';
if (!is_file($schemaFile)) {
    echo "Error: Schema file not found at {$schemaFile}" . PHP_EOL;
    exit(1);
}

echo "Executing schema from {$schemaFile}..." . PHP_EOL;
$sql = file_get_contents($schemaFile);
$pdo->exec($sql);

echo "Database initialized and seeded successfully!" . PHP_EOL;
