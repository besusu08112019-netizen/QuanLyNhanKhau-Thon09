<?php

namespace App\Models;

use App\Core\BaseModel;

final class SystemSetting extends BaseModel
{
    private array $allowed = ['systemName','logoUrl','backgroundUrl','backgroundImages','backgroundInterval','introImageUrl','unitName','hamletName','communeName','slogan','softwareVersion','introTitle','historyTitle','hamletHistory','introduction','phone','email','address','website','copyright','reportSigner','supportEmail','maintenanceMessage'];

    public function all(): array
    {
        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        foreach ($this->allowed as $key) if (!array_key_exists($key, $settings)) $settings[$key] = $this->defaultValue($key);
        return $settings;
    }

    public function updateMany(array $data, int $userId): array
    {
        foreach ($this->allowed as $key) {
            if (!array_key_exists($key, $data)) continue;
            $value = trim((string) $data[$key]);
            $this->execute('INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (:key,:value,:user) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)', ['key' => $key, 'value' => $value, 'user' => $userId]);
        }
        return $this->all();
    }

    private function defaultValue(string $key): string
    {
        return match ($key) {
            'systemName' => 'Hệ thống Quản lý Hành chính',
            'hamletName' => 'Thôn 09',
            'communeName' => 'Xã Hồng Phong',
            'slogan' => 'Vì Nhân dân phục vụ',
            'softwareVersion' => 'v2.0',
            'introTitle' => 'Giới thiệu Thôn 09 - Xã Hồng Phong',
            'historyTitle' => 'Lịch sử hình thành Thôn 09',
            'backgroundInterval' => '6000',
            'website' => 'nhankhauthon09.com',
            'copyright' => '© Thôn 09 - Xã Hồng Phong',
            default => '',
        };
    }
}

