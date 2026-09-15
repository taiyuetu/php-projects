<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

/**
 * Restricts a route to a specific role, e.g. 'admin'. Configure the
 * required role by extending this class (see AdminOnlyMiddleware)
 * since middleware are instantiated with no constructor args by the
 * Router — keeps the Router's pipeline simple.
 */
abstract class RoleMiddleware implements MiddlewareInterface
{
    abstract protected function role(): string;

    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check() || !Auth::hasRole($this->role())) {
            return $request->isJson()
                ? Response::json(['error' => 'Forbidden.'], 403)
                : Response::html('403 Forbidden', 403);
        }

        return $next($request);
    }
}
