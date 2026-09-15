<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\User;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('profile.index', [
            'user'    => Auth::user(),
            'success' => Session::getFlash('success'),
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $data = $this->validate($request, [
            'current_password' => 'required',
            'new_password'     => 'required|min:8',
            'confirm_password' => 'required',
        ]);

        $user = Auth::user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        if ($data['new_password'] !== $data['confirm_password']) {
            throw new ValidationException([
                'confirm_password' => ['New password and confirmation do not match.'],
            ]);
        }

        if (!password_verify($data['current_password'], $user['password_hash'])) {
            throw new ValidationException([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        User::update($user['id'], [
            'password_hash' => password_hash($data['new_password'], PASSWORD_BCRYPT),
        ]);

        Logger::audit('password_changed', (int) $user['id'], []);
        Session::flash('success', 'Your password has been changed successfully.');

        return $this->redirect('/profile');
    }
}
