<?php

namespace App\Models;

use App\Core\BaseModel;

final class PushSubscription extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint VARCHAR(700) NOT NULL,
  p256dh VARCHAR(255) NULL,
  auth VARCHAR(255) NULL,
  user_agent VARCHAR(255) NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  last_seen_at DATETIME NULL,
  last_push_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_subscription_endpoint (endpoint),
  KEY idx_push_subscription_user (village_id, user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function saveForUser(int $userId, array $subscription, string $userAgent = ''): array
    {
        $this->ensureSchema();
        $endpoint = trim((string)($subscription['endpoint'] ?? ''));
        if ($endpoint === '' || !preg_match('#^https://#i', $endpoint)) {
            throw new \RuntimeException('Endpoint thÃ´ng bÃ¡o khÃ´ng há»£p lá»‡');
        }
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        $params = $this->withTenant([
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => trim((string)($keys['p256dh'] ?? '')) ?: null,
            'auth' => trim((string)($keys['auth'] ?? '')) ?: null,
            'user_agent' => mb_substr($userAgent, 0, 255, 'UTF-8'),
        ]);
        $this->execute('INSERT INTO push_subscriptions (village_id,user_id,endpoint,p256dh,auth,user_agent,status,last_seen_at)
            VALUES (:village_id,:user_id,:endpoint,:p256dh,:auth,:user_agent,"ACTIVE",NOW())
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), village_id=VALUES(village_id), p256dh=VALUES(p256dh), auth=VALUES(auth), user_agent=VALUES(user_agent), status="ACTIVE", last_seen_at=NOW()', $params);
        return ['endpoint' => $endpoint, 'active' => true];
    }

    public function removeForUser(int $userId, string $endpoint): void
    {
        $this->ensureSchema();
        $this->execute('UPDATE push_subscriptions SET status="DELETED", updated_at=NOW() WHERE user_id=:user_id AND endpoint=:endpoint AND ' . $this->tenantWhere('push_subscriptions'), $this->withTenant(['user_id' => $userId, 'endpoint' => $endpoint]));
    }

    public function activeForUser(int $userId): array
    {
        $this->ensureSchema();
        return $this->fetchAll('SELECT * FROM push_subscriptions WHERE user_id=:user_id AND status="ACTIVE" AND ' . $this->tenantWhere('push_subscriptions') . ' ORDER BY last_seen_at DESC, id DESC', $this->withTenant(['user_id' => $userId]));
    }

    public function touchPushed(int $id): void
    {
        $this->execute('UPDATE push_subscriptions SET last_push_at=NOW() WHERE id=:id AND ' . $this->tenantWhere('push_subscriptions'), $this->withTenant(['id' => $id]));
    }

    public function deactivate(int $id): void
    {
        $this->execute('UPDATE push_subscriptions SET status="DELETED", updated_at=NOW() WHERE id=:id AND ' . $this->tenantWhere('push_subscriptions'), $this->withTenant(['id' => $id]));
    }
}
