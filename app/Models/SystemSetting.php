<?php

namespace App\Models;

use App\Core\BaseModel;

final class SystemSetting extends BaseModel
{
    private array $allowed = ['systemName','logoUrl','backgroundUrl','unitName','hamletName','communeName','phone','email','address','reportSigner','supportEmail','maintenanceMessage'];

    public function all(): array
    {
        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
        foreach ($this->allowed as $key) if (!array_key_exists($key, $settings)) $settings[$key] = '';
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
}
