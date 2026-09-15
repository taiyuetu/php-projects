<?php
/**
 * Front Controller
 * Every request is routed through this single entry point.
 */
require_once __DIR__ . '/../config/config.php';

// PHP's built-in server does not read .htaccess. When this file is used as
// its router script, forward extensionless requests to the MVC router while
// allowing real public assets to be served normally.
if (PHP_SAPI === 'cli-server') {
    $requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $publicFile = realpath(__DIR__ . $requestPath);
    $publicRoot = realpath(__DIR__);
    if ($requestPath !== '/index.php' && $publicFile !== false && $publicRoot !== false
        && str_starts_with($publicFile, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($publicFile)) {
        return false;
    }
    if (empty($_GET['url'])) {
        $_GET['url'] = trim($requestPath, '/');
    }
}

$router = new Router();
$url = $_GET['url'] ?? '';
$router->dispatch($url);
