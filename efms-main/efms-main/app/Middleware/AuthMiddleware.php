<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            return $request->isJson()
                ? Response::json(['error' => 'Unauthenticated.'], 401)
                : Response::redirect('/login');
        }

        return $next($request);
    }
}
