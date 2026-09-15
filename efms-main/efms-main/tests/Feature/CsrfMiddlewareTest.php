<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\CsrfMiddleware;
use Tests\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    public function testGetRequestsBypassCsrf(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET']);
        $middleware = new CsrfMiddleware();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::html('OK');
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->status());
    }

    public function testPostWithoutTokenFailsWith419(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'POST']);
        $middleware = new CsrfMiddleware();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::html('OK');
        });

        $this->assertFalse($called);
        $this->assertEquals(419, $response->status());
    }

    public function testPostWithValidTokenSucceeds(): void
    {
        $token = Csrf::token();
        $request = new Request([], ['_token' => $token], ['REQUEST_METHOD' => 'POST']);
        $middleware = new CsrfMiddleware();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return Response::html('OK');
        });

        $this->assertTrue($called);
        $this->assertEquals(200, $response->status());
    }
}
