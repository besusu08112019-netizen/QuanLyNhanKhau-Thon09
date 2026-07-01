<?php

namespace App\Controllers;

use App\Core\BaseController;

final class AuthController extends BaseController
{
    public function setup(): void
    {
        $email = trim((string) $this->input('email', $this->input('username', '')));
        $name = trim((string) $this->input('displayName', $email));
        $password = (string) $this->input('password', '');
        if (!$email || strlen($password) < 8) $this->fail('Email và mật khẩu tối thiểu 8 ký tự là bắt buộc');
        $user = $this->users()->createFirstAdmin($email, $name, $password);
        $this->audit($user, 'user', 'create', 'Tạo tài khoản quản trị đầu tiên', $user['id']);
        $this->ok($this->users()->publicUser($user));
    }

    public function login(): void
    {
        try {
            $result = $this->users()->login((string) $this->input('username', $this->input('email', '')), (string) $this->input('password', ''));
            $this->audit($result['user'], 'user', 'read', 'Đăng nhập hệ thống', $result['user']['id']);
            $this->ok($result);
        } catch (\Throwable $e) { $this->fail($e->getMessage(), 401); }
    }

    public function logout(): void
    {
        $user = $this->user();
        $this->verifyCsrfToken();
        $token = $this->request->bearerToken();
        if ($token) $this->users()->revoke($token);
        $this->audit($user, 'user', 'read', 'Đăng xuất hệ thống', $user['id']);
        $this->ok(['loggedOutAt' => date('c')]);
    }

    public function me(): void { $this->ok($this->users()->publicUser($this->user())); }
}
