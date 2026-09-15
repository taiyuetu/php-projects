<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        $method = $request->method();

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');

            if (!Csrf::validate($token)) {
                return $request->isJson()
                    ? Response::json(['error' => 'Invalid or missing CSRF token.'], 419)
                    : Response::html('<h1>419 — Page Expired</h1><p>Invalid or expired CSRF token. Please return to the previous page and try again.</p>', 419);
            }
        }

        return $next($request);
    }
}
