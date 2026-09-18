<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Setting;
use App\Models\User;

/**
 * 系统设置控制器（仅管理员）
 * 管理应用信息、公司信息、货币符号、单据备注，以及当前登录用户的账户信息。
 */
class SettingController extends Controller
{
    public function __construct()
    {
        Auth::requireAdmin();
    }

    public function index(): void
    {
        $this->view('settings/index', [
            'title'    => '系统设置',
            'settings' => Setting::allSettings(),
            'user'     => Auth::user(),
        ]);
    }

    public function update(): void
    {
        $this->verifyCsrf();

        // ---- 应用 / 公司 / 货币设置 ----
        $values = [];
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $values[$key] = trim((string) $this->input($key, ''));
        }
        if ($values['app_name'] === '') {
            $this->flash('error', '应用名称不能为空。');
            $this->redirect('/settings');
        }
        if ($values['currency_symbol'] === '') {
            $this->flash('error', '货币符号不能为空。');
            $this->redirect('/settings');
        }
        Setting::set($values);

        // ---- 当前用户账户信息 ----
        $name     = trim((string) $this->input('user_name', ''));
        $email    = trim((string) $this->input('user_email', ''));
        $password = (string) $this->input('user_password', '');

        if ($name === '' || $email === '') {
            $this->flash('error', '姓名和邮箱不能为空。');
            $this->redirect('/settings');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', '请输入有效的邮箱地址。');
            $this->redirect('/settings');
        }
        if (User::exists('email', $email, (int) Auth::user()['id'])) {
            $this->flash('error', '该邮箱已被其他用户使用。');
            $this->redirect('/settings');
        }
        if ($password !== '' && strlen($password) < 6) {
            $this->flash('error', '新密码至少需要 6 个字符。');
            $this->redirect('/settings');
        }

        $data = ['name' => $name, 'email' => $email];
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        User::update(Auth::user()['id'], $data);

        // Keep the session in sync with the new profile.
        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['email'] = $email;

        $this->flash('success', '设置已保存。');
        $this->redirect('/settings');
    }
}
