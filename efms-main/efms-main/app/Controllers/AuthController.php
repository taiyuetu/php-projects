<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth.login', ['error' => null], layout: null);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $throttleKey = 'login:' . strtolower($data['email']) . '|' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->view('auth.login', [
                'error' => "Too many failed login attempts. Please wait {$seconds} seconds before trying again.",
            ], layout: null);
        }

        if (!Auth::attempt($data['email'], $data['password'])) {
            RateLimiter::hit($throttleKey, 60);
            return $this->view('auth.login', ['error' => 'Invalid credentials.'], layout: null);
        }

        RateLimiter::clear($throttleKey);

        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return $this->redirect('/login');
    }
}
