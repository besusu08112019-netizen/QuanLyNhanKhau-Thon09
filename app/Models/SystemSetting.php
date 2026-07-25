<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\TenantConfig;
use App\Core\TenantContext;

final class SystemSetting extends BaseModel
{
    private array $allowed = ['systemName','logoUrl','backgroundUrl','backgroundImages','backgroundInterval','introImageUrl','unitName','hamletName','communeName','slogan','softwareVersion','introTitle','historyTitle','hamletHistory','introduction','phone','email','address','website','copyright','reportSigner','supportEmail','maintenanceMessage','themeColor','backgroundColor','manifestId'];

    public function all(): array
    {
        $rows = $this->columnExists('settings', 'village_id')
            ? $this->fetchAll('SELECT setting_key, setting_value FROM settings WHERE village_id = :village_id ORDER BY setting_key', ['village_id' => TenantContext::id()])
            : $this->fetchAll('SELECT setting_key, setting_value FROM settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        foreach ($this->allowed as $key) {
            if (!array_key_exists($key, $settings)) {
                $settings[$key] = $this->defaultValue($key);
            }
        }
        return TenantConfig::publicSettings($settings);
    }

    public function updateMany(array $data, int $userId): array
    {
        foreach ($this->allowed as $key) {
            if (!array_key_exists($key, $data)) continue;
            $value = $key === 'backgroundImages'
                ? $this->singleBackgroundImageValue($data[$key])
                : trim((string) $data[$key]);
            if ($this->columnExists('settings', 'village_id')) {
                $this->execute('INSERT INTO settings (village_id, setting_key, setting_value, updated_by) VALUES (:village_id,:key,:value,:user) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)', ['village_id' => TenantContext::id(), 'key' => $key, 'value' => $value, 'user' => $userId]);
            } else {
                $this->execute('INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (:key,:value,:user) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)', ['key' => $key, 'value' => $value, 'user' => $userId]);
            }
        }
        return $this->all();
    }

    private function defaultValue(string $key): string
    {
        $defaults = TenantConfig::defaults();
        return $defaults[$key] ?? '';
    }

    private function singleBackgroundImageValue(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $url = trim((string) $item);
                if ($url !== '') return $url;
            }
            return '';
        }

        $text = trim((string) $value);
        if ($text === '') return '';

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                $url = trim((string) $item);
                if ($url !== '') return $url;
            }
            return '';
        }

        foreach (preg_split('/[\r\n,]+/', $text) ?: [] as $item) {
            $url = trim((string) $item);
            if ($url !== '') return $url;
        }

        return '';
    }
}
