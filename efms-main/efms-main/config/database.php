<?php

declare(strict_types=1);

return [
    'driver'   => $_ENV['DB_DRIVER'] ?? 'sqlite',
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'     => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? (dirname(__DIR__) . '/database/database.sqlite'),
    'username' => $_ENV['DB_USERNAME'] ?? '',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];
