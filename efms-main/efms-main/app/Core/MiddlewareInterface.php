<?php

declare(strict_types=1);

namespace App\Core;

interface MiddlewareInterface
{
    /**
     * @param \Closure(Request): Response $next
     */
    public function handle(Request $request, \Closure $next): Response;
}
