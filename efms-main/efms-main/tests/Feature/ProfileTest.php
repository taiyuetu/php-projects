<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\ProfileController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\User;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function testShowProfileRendersSuccessfully(): void
    {
        Auth::attempt('admin@example.com', 'password');

        $controller = new ProfileController();
        $response = $controller->show(new Request());

        $this->assertEquals(200, $response->status());
        $this->assertStringContainsString('admin@example.com', $response->body());
        $this->assertStringContainsString('Change Password', $response->body());
    }

    public function testUpdatePasswordWithIncorrectCurrentPasswordThrows(): void
    {
        Auth::attempt('admin@example.com', 'password');

        $controller = new ProfileController();
        $request = new Request([], [
            'current_password' => 'wrongcurrent',
            'new_password'     => 'newpassword123',
            'confirm_password' => 'newpassword123',
        ]);

        $this->expectException(ValidationException::class);
        $controller->updatePassword($request);
    }

    public function testUpdatePasswordWithMismatchedConfirmationThrows(): void
    {
        Auth::attempt('admin@example.com', 'password');

        $controller = new ProfileController();
        $request = new Request([], [
            'current_password' => 'password',
            'new_password'     => 'newpassword123',
            'confirm_password' => 'differentpassword',
        ]);

        $this->expectException(ValidationException::class);
        $controller->updatePassword($request);
    }

    public function testUpdatePasswordSuccess(): void
    {
        Auth::attempt('admin@example.com', 'password');

        $controller = new ProfileController();
        $request = new Request([], [
            'current_password' => 'password',
            'new_password'     => 'supersecret123',
            'confirm_password' => 'supersecret123',
        ]);

        $response = $controller->updatePassword($request);
        $this->assertEquals(302, $response->status());

        // Verify that old password no longer works and new password works
        Auth::logout();
        $this->assertFalse(Auth::attempt('admin@example.com', 'password'));
        $this->assertTrue(Auth::attempt('admin@example.com', 'supersecret123'));

        // Restore original password for subsequent tests
        $controller->updatePassword(new Request([], [
            'current_password' => 'supersecret123',
            'new_password'     => 'password',
            'confirm_password' => 'password',
        ]));
    }
}
