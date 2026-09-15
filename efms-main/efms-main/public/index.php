<?php

declare(strict_types=1);

/**
 * Front controller. Every HTTP request enters the framework here.
 * Apache/Nginx should route all non-file requests to this script
 * (see /public/.htaccess for Apache).
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use Dotenv\Dotenv;

if (is_file(__DIR__ . '/../.env')) {
    Dotenv::createImmutable(__DIR__ . '/..')->load();
}

App::run();
