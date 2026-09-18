<?php

/**
 * Migration: Create the settings (key/value) table.
 * Run once:  php database/add_settings_table.php
 */
$dbPath = __DIR__ . '/database.sqlite';
if (!file_exists($dbPath)) {
    die("Database not found at {$dbPath}\n");
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if table already exists
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetchAll(PDO::FETCH_COLUMN);
if (in_array('settings', $tables)) {
    echo "Table 'settings' already exists. Skipping.\n";
    exit(0);
}

$pdo->exec("
    CREATE TABLE settings (
        skey       TEXT PRIMARY KEY,
        svalue     TEXT,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");
echo "Table 'settings' created.\n";
