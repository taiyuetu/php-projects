<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', ['title' => '登录'], layout: null);
    }

    public function login(): void
    {
        $this->verifyCsrf();

        $email = $this->input('email');
        $password = $this->input('password');

        if (Auth::attempt($email, $password)) {
            $this->redirect('/dashboard');
        }

        $this->flash('error', '邮箱或密码错误。');
        $this->redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
