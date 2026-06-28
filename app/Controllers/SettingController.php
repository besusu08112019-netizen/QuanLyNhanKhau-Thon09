<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\SystemSetting;

final class SettingController extends BaseController
{
    private SystemSetting $settings;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->settings = new SystemSetting();
    }

    public function index(): void
    {
        $this->requirePermission('settings', 'read');
        $this->ok($this->settings->all());
    }

    public function update(): void
    {
        $user = $this->requirePermission('settings', 'update');
        $settings = $this->settings->updateMany($this->input(), (int) $user['id']);
        $this->audit($user, 'settings', 'update', 'Cập nhật cấu hình hệ thống');
        $this->ok($settings);
    }
}
