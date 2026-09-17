<?php
/**
 * Application Configuration
 * Edit these values to match your environment.
 */

// ---- Database ----
// SQLite keeps local development self-contained; the database file is created
// automatically on first request and seeded from database/schema.sql.
define('DB_PATH', __DIR__ . '/../database/hrms.sqlite');

// ---- Application ----
// Base URL WITHOUT trailing slash. e.g. http://localhost/hrms/public
$configuredBaseUrl = getenv('APP_URL');
if ($configuredBaseUrl === false || $configuredBaseUrl === '') {
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
    $detectedBaseUrl = $scriptPath ? str_replace('\\', '/', dirname(str_replace('\\', '/', $scriptPath))) : '/public';
    $configuredBaseUrl = $detectedBaseUrl === '/' || $detectedBaseUrl === '.' ? '' : $detectedBaseUrl;
}
define('BASE_URL', rtrim($configuredBaseUrl, '/'));
define('APP_NAME', 'HRMS - 人力资源管理系统');
define('APP_VERSION', '1.1.0');

// Default controller/action when no route is given
define('DEFAULT_CONTROLLER', 'Dashboard');
define('DEFAULT_ACTION', 'index');

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Error reporting (turn off in production) ----
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

// ---- Timezone ----
date_default_timezone_set('Asia/Singapore');

// ---- Autoload core + app classes ----
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../core/' . $class . '.php',
        __DIR__ . '/../app/controllers/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
