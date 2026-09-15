<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return View::e($value);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        $old = Session::getFlash('old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors(?string $key = null): array|string|null
    {
        $errors = Session::getFlash('errors', []);
        if ($key === null) {
            return $errors;
        }

        return $errors[$key][0] ?? null;
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        $errors = Session::getFlash('errors', []);
        return !empty($errors[$key]);
    }
}
