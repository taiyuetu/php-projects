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
                } else {
                    self::ensureAdditionalTables(self::$instance);
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

    private static function ensureAdditionalTables(PDO $pdo): void
    {
        $hasOffboarding = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'offboarding_employees'")->fetchColumn();
        if (!$hasOffboarding) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS onboarding (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    employee_id INTEGER NOT NULL,
                    start_date TEXT NOT NULL,
                    target_completion_date TEXT,
                    status TEXT NOT NULL DEFAULT 'In Progress' CHECK (status IN ('Pending', 'In Progress', 'Completed')),
                    notes TEXT,
                    completed_at TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS onboarding_tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    onboarding_id INTEGER NOT NULL,
                    task_name TEXT NOT NULL,
                    description TEXT,
                    is_completed INTEGER NOT NULL DEFAULT 0 CHECK (is_completed IN (0, 1)),
                    completed_at TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (onboarding_id) REFERENCES onboarding(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS offboarding (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    employee_id INTEGER NOT NULL,
                    exit_date TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'In Progress' CHECK (status IN ('Pending', 'In Progress', 'Completed')),
                    notes TEXT,
                    completed_at TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS offboarding_tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    offboarding_id INTEGER NOT NULL,
                    task_name TEXT NOT NULL,
                    description TEXT,
                    is_completed INTEGER NOT NULL DEFAULT 0 CHECK (is_completed IN (0, 1)),
                    completed_at TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (offboarding_id) REFERENCES offboarding(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS offboarding_employees (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    employee_id INTEGER,
                    employee_code TEXT NOT NULL,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT,
                    department_name TEXT,
                    designation TEXT,
                    hire_date TEXT,
                    exit_date TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    final_settlement_status TEXT DEFAULT 'Completed',
                    notes TEXT,
                    completed_tasks_summary TEXT,
                    offboarded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    offboarded_by INTEGER,
                    FOREIGN KEY (offboarded_by) REFERENCES users(id) ON DELETE SET NULL
                );
            ");
        }
    }

    // Prevent cloning / unserialization of the instance
    private function __construct() {}
    private function __clone() {}
}
