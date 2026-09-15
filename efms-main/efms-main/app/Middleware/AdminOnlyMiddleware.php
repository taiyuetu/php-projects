<?php

declare(strict_types=1);

namespace App\Middleware;

final class AdminOnlyMiddleware extends RoleMiddleware
{
    protected function role(): string
    {
        return 'admin';
    }
}
