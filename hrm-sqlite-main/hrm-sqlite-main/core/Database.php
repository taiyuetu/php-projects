<?php
/** SQLite PDO singleton with first-run schema initialization. */
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $databaseExists = file_exists(DB_PATH);
            $dsn = 'sqlite:' . DB_PATH;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, null, null, $options);
                self::$instance->exec('PRAGMA foreign_keys = ON');
                if (!$databaseExists || !self::$instance->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn()) {
                    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
                    if ($schema === false || self::$instance->exec($schema) === false) {
                        throw new RuntimeException('Unable to initialize the SQLite schema.');
                    }
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                die('Database connection failed. Check the application configuration.');
            } catch (RuntimeException $e) {
                error_log($e->getMessage());
                die('Database initialization failed. Check database/schema.sql.');
            }
        }
        return self::$instance;
    }

    // Prevent cloning / unserialization of the instance
    private function __construct() {}
    private function __clone() {}
}
