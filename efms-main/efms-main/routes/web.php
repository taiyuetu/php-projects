<?php

declare(strict_types=1);

/**
 * @var \App\Core\Router $router  (injected by App::run())
 *
 * This file is the map of the whole application. To add a new
 * module: create its Model + Controller + Views, then add one line
 * here (or one $router->resource(...) call for standard CRUD).
 */

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\InvoiceController;
use App\Controllers\ProfileController;
use App\Controllers\TransactionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

// All state-changing routes (POST/PUT/PATCH/DELETE) are protected by CSRF.
$router->group('', [CsrfMiddleware::class], function ($router) {

    // ---- Public routes -----------------------------------------------------
    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);

    // ---- Authenticated application -----------------------------------------
    $router->group('', [AuthMiddleware::class], function ($router) {
        $router->post('/logout', [AuthController::class, 'logout']);

        $router->get('/', [DashboardController::class, 'index']);
        $router->get('/dashboard', [DashboardController::class, 'index']);

        // Profile & change password
        $router->get('/profile', [ProfileController::class, 'show']);
        $router->post('/profile/password', [ProfileController::class, 'updatePassword']);

        // Chart of accounts: full CRUD via one line.
        $router->resource('accounts', AccountController::class);

        // Journal entries are append-only (no edit/destroy) — registered explicitly.
        $router->get('/transactions', [TransactionController::class, 'index']);
        $router->get('/transactions/create', [TransactionController::class, 'create']);
        $router->post('/transactions', [TransactionController::class, 'store']);
        $router->get('/transactions/{id}', [TransactionController::class, 'show']);

        // Invoices: CRUD create/list/show plus a custom "post to ledger" action.
        $router->get('/invoices', [InvoiceController::class, 'index']);
        $router->get('/invoices/create', [InvoiceController::class, 'create']);
        $router->post('/invoices', [InvoiceController::class, 'store']);
        $router->get('/invoices/{id}', [InvoiceController::class, 'show']);
        $router->post('/invoices/{id}/post', [InvoiceController::class, 'post']);
    });

});
