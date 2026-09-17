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

        $hasRecruitment = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'candidates'")->fetchColumn();
        if (!$hasRecruitment) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS job_openings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    department_id INTEGER,
                    employment_type TEXT NOT NULL DEFAULT 'Full-Time' CHECK (employment_type IN ('Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote')),
                    openings_count INTEGER NOT NULL DEFAULT 1,
                    location TEXT DEFAULT 'Singapore',
                    salary_range TEXT,
                    description TEXT,
                    requirements TEXT,
                    status TEXT NOT NULL DEFAULT 'Open' CHECK (status IN ('Open', 'Paused', 'Closed')),
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS candidates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_id INTEGER NOT NULL,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT,
                    resume_url TEXT,
                    linkedin_url TEXT,
                    experience_years NUMERIC DEFAULT 0,
                    current_company TEXT,
                    stage TEXT NOT NULL DEFAULT 'Applied' CHECK (stage IN ('Applied', 'Screening', 'Interview', 'Offered', 'Hired', 'Rejected')),
                    rating INTEGER DEFAULT 0 CHECK (rating BETWEEN 0 AND 5),
                    notes TEXT,
                    applied_date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    hired_employee_id INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (job_id) REFERENCES job_openings(id) ON DELETE CASCADE,
                    FOREIGN KEY (hired_employee_id) REFERENCES employees(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS candidate_notes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    candidate_id INTEGER NOT NULL,
                    user_id INTEGER,
                    stage TEXT NOT NULL,
                    note TEXT NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                );
            ");
        }
        $hasOnboardingApplications = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'onboarding_applications'")->fetchColumn();
        if (!$hasOnboardingApplications) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS onboarding_invites (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    token TEXT NOT NULL UNIQUE,
                    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
                    created_by INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS onboarding_applications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invite_id INTEGER,
                    first_name TEXT NOT NULL,
                    last_name TEXT NOT NULL,
                    email TEXT NOT NULL,
                    phone TEXT NOT NULL,
                    gender TEXT,
                    dob TEXT,
                    id_number TEXT,
                    ethnicity TEXT,
                    household_address TEXT,
                    emergency_contact TEXT,
                    emergency_relation TEXT,
                    emergency_phone TEXT,
                    education TEXT,
                    address TEXT,
                    department_id INTEGER,
                    designation TEXT,
                    expected_salary NUMERIC NOT NULL DEFAULT 0,
                    notes TEXT,
                    status TEXT NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
                    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at TEXT,
                    reviewed_by INTEGER,
                    review_comment TEXT,
                    hired_employee_id INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (invite_id) REFERENCES onboarding_invites(id) ON DELETE SET NULL,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
                    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (hired_employee_id) REFERENCES employees(id) ON DELETE SET NULL
                );
            ");
        }
    }

    // Prevent cloning / unserialization of the instance
    private function __construct() {}
    private function __clone() {}
}
