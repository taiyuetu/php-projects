<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function testLoginSucceedsWithValidCredentials(): void
    {
        $result = Auth::attempt('admin@example.com', 'password');
        $this->assertTrue($result);
        $this->assertTrue(Auth::check());

        $user = Auth::user();
        $this->assertNotNull($user);
        $this->assertEquals('admin@example.com', $user['email']);
        $this->assertEquals('admin', $user['role']);
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $result = Auth::attempt('admin@example.com', 'wrongpassword');
        $this->assertFalse($result);
        $this->assertFalse(Auth::check());
    }

    public function testRateLimiterBlocksAfterConfiguredAttempts(): void
    {
        $key = 'test:login:rate:limit';
        RateLimiter::clear($key);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
            RateLimiter::hit($key, 60);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->assertGreaterThan(0, RateLimiter::availableIn($key));

        RateLimiter::clear($key);
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }

    public function testLogoutClearsSession(): void
    {
        Auth::attempt('admin@example.com', 'password');
        $this->assertTrue(Auth::check());

        Auth::logout();
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
    }
}
