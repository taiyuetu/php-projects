<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Auth
 *
 * Session-based authentication helper. Deliberately framework-agnostic
 * (no dependency on Request) so it can be called from anywhere —
 * controllers, middleware, views.
 */
final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::query()->where('email', '=', $email)->first();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Logger::audit('login_failed', null, ['email' => $email]);
            return false;
        }

        self::login((int) $user['id']);
        Logger::audit('login_success', (int) $user['id'], []);

        return true;
    }

    public static function login(int $userId): void
    {
        Session::start();
        Session::regenerate(true);
        Session::set('user_id', $userId);
    }

    public static function logout(): void
    {
        Session::start();
        Logger::audit('logout', self::id(), []);
        Session::remove('user_id');
        Session::regenerate(true);
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        return User::find($id);
    }

    /** Very small role check; roles column is a comma-free single value e.g. 'admin','accountant','viewer'. */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? null) === $role;
    }
}
