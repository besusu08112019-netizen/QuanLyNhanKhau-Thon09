<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\DigitalProfile;

final class ProfileController extends BaseController
{
    private DigitalProfile $profiles;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->profiles = new DigitalProfile();
    }

    public function household(string $id): void
    {
        $this->requirePermission('household', 'read');
        $profile = $this->profiles->household((int) $id);
        $profile ? $this->ok($profile) : $this->fail('Không tìm thấy hồ sơ hộ gia đình', 404);
    }

    public function citizen(string $id): void
    {
        $this->requirePermission('citizen', 'read');
        $profile = $this->profiles->citizen((int) $id);
        $profile ? $this->ok($profile) : $this->fail('Không tìm thấy hồ sơ nhân khẩu', 404);
    }

    public function timeline(string $module, string $id): void
    {
        $module = $module === 'persons' ? 'citizen' : rtrim($module, 's');
        if (!in_array($module, ['household', 'citizen'], true)) {
            $this->fail('Loại hồ sơ không hợp lệ');
        }
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'read');
        $profile = $module === 'citizen'
            ? $this->profiles->citizen((int) $id)
            : $this->profiles->household((int) $id);
        $profile ? $this->ok($profile['timeline'] ?? []) : $this->fail('Không tìm thấy hồ sơ', 404);
    }
}
