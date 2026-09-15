<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Database;
use App\Core\Session;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config is loaded
        Config::load(dirname(__DIR__) . '/config');

        $testDb = dirname(__DIR__) . '/database/test.sqlite';
        if (!self::$migrated || !is_file($testDb)) {
            if (is_file($testDb)) {
                @unlink($testDb);
            }
            $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
            $pdo = new \PDO("sqlite:{$testDb}");
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec($schema);
            self::$migrated = true;
        }

        Database::resetInstance();
        Session::start();
        $_SESSION = [];
    }
}
