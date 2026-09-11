<?php

namespace App\Models;

use App\Core\BaseModel;

final class NotificationCenter extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    public function assertSchemaReady(): void
    {
        $this->assertColumns('notification_states', [
            'id' => ['bigint(20) unsigned', 'NO'],
            'village_id' => ['bigint(20) unsigned', 'NO'],
            'user_id' => ['bigint(20) unsigned', 'NO'],
            'notification_key' => ['varchar(160)', 'NO'],
            'read_at' => ['datetime', 'YES'],
            'dismissed_at' => ['datetime', 'YES'],
            'created_at' => ['datetime', 'NO'],
            'updated_at' => ['datetime', 'YES'],
        ]);
        $this->assertIndex('notification_states', 'uq_notification_state_user_key', ['village_id', 'user_id', 'notification_key']);
        $this->assertIndex('notification_states', 'idx_notification_states_village', ['village_id']);
        $this->assertIndex('notification_states', 'idx_notification_states_user_read', ['user_id', 'read_at']);
        $this->assertIndex('notification_states', 'idx_notification_states_user_dismissed', ['user_id', 'dismissed_at']);
    }

    public function list(int $userId, array $filters = []): array
    {
        $this->ensureSchema();
        $items = $this->generated();
        $states = $this->states($userId, array_column($items, 'key'));
        foreach ($items as &$item) {
            $state = $states[$item['key']] ?? [];
            $item['read_at'] = $state['read_at'] ?? null;
            $item['dismissed_at'] = $state['dismissed_at'] ?? null;
            $item['is_read'] = !empty($item['read_at']);
        }
        unset($item);
        $includeDismissed = !empty($filters['include_dismissed']) || !empty($filters['includeDismissed']);
        if (!$includeDismissed) $items = array_values(array_filter($items, fn($item) => empty($item['dismissed_at'])));
        if (trim((string)($filters['unread'] ?? '')) === '1') $items = array_values(array_filter($items, fn($item) => empty($item['read_at'])));
        usort($items, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        $limit = min(max((int)($filters['limit'] ?? 20), 5), 100);
        return ['items' => array_slice($items, 0, $limit), 'unread' => count(array_filter($items, fn($item) => empty($item['read_at']))), 'total' => count($items), 'generatedAt' => date('c')];
    }

    public function markRead(int $userId, string $key): void
    {
        $this->ensureSchema();
        $this->execute('INSERT INTO notification_states (village_id, user_id, notification_key, read_at) VALUES (:village_id,:user_id,:notification_key,NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at, NOW()), dismissed_at=NULL', $this->withTenant(['user_id' => $userId, 'notification_key' => $key]));
    }

    public function dismiss(int $userId, string $key): void
    {
        $this->ensureSchema();
        $this->execute('INSERT INTO notification_states (village_id, user_id, notification_key, read_at, dismissed_at) VALUES (:village_id,:user_id,:notification_key,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at, NOW()), dismissed_at=NOW()', $this->withTenant(['user_id' => $userId, 'notification_key' => $key]));
    }

    public function markAllRead(int $userId): void
    {
        foreach ($this->generated() as $item) $this->markRead($userId, (string)$item['key']);
    }

    private function generated(): array
    {
        return array_values(array_filter(array_merge(
            $this->complaintNotifications(),
            $this->workTaskNotifications(),
            $this->calendarNotifications(),
            $this->documentNotifications(),
            $this->backupNotifications()
        ), fn($item) => (int)($item['count'] ?? 0) > 0));
    }

    private function complaintNotifications(): array
    {
        if (!$this->tableExists('complaints')) return [];
        $new = $this->count('complaints c', 'c.soft_status <> "DELETED" AND ' . $this->tenantWhere('c', 'complaints') . ' AND c.closed_at IS NULL AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $overdue = $this->count('complaints c', 'c.soft_status <> "DELETED" AND ' . $this->tenantWhere('c', 'complaints') . ' AND c.closed_at IS NULL AND c.due_at IS NOT NULL AND c.due_at < NOW()');
        return [
            $this->item('complaints_new', 'Phan anh moi', 'Co phan anh moi can tiep nhan/xu ly', $new, 'high', 'complaints', 'fa-comments'),
            $this->item('complaints_overdue', 'Phan anh qua han', 'Co phan anh da qua han xu ly', $overdue, 'urgent', 'complaints', 'fa-triangle-exclamation'),
        ];
    }

    private function workTaskNotifications(): array
    {
        if (!$this->tableExists('work_tasks')) return [];
        $new = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND ' . $this->tenantWhere('wt', 'work_tasks') . ' AND wt.completed_at IS NULL AND wt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $due = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND ' . $this->tenantWhere('wt', 'work_tasks') . ' AND wt.completed_at IS NULL AND wt.due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)');
        $overdue = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND ' . $this->tenantWhere('wt', 'work_tasks') . ' AND wt.completed_at IS NULL AND wt.due_at IS NOT NULL AND wt.due_at < NOW()');
        return [
            $this->item('work_tasks_new', 'Cong viec moi', 'Co cong viec moi duoc tao', $new, 'medium', 'workTasks', 'fa-list-check'),
            $this->item('work_tasks_due_soon', 'Cong viec gan den han', 'Cong viec se den han trong 3 ngay', $due, 'high', 'workTasks', 'fa-clock'),
            $this->item('work_tasks_overdue', 'Cong viec qua han', 'Co cong viec da qua han', $overdue, 'urgent', 'workTasks', 'fa-triangle-exclamation'),
        ];
    }

    private function calendarNotifications(): array
    {
        if (!$this->tableExists('calendar_events')) return [];
        $today = $this->count('calendar_events ce', 'ce.soft_status <> "DELETED" AND ' . $this->tenantWhere('ce', 'calendar_events') . ' AND ce.status="SCHEDULED" AND DATE(ce.start_at)=CURDATE()');
        $upcoming = $this->count('calendar_events ce', 'ce.soft_status <> "DELETED" AND ' . $this->tenantWhere('ce', 'calendar_events') . ' AND ce.status="SCHEDULED" AND ce.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)');
        return [
            $this->item('calendar_today', 'Lich hom nay', 'Co lich cong tac trong ngay', $today, 'high', 'workCalendar', 'fa-calendar-day'),
            $this->item('calendar_upcoming', 'Lich sap toi', 'Co lich cong tac trong 3 ngay toi', $upcoming, 'medium', 'workCalendar', 'fa-calendar-days'),
        ];
    }

    private function documentNotifications(): array
    {
        if (!$this->tableExists('village_documents')) return [];
        $recent = $this->count('village_documents vd', 'vd.status <> "DELETED" AND ' . $this->tenantWhere('vd', 'village_documents') . ' AND vd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        return [$this->item('documents_recent', 'Van ban moi', 'Co van ban moi duoc cap nhat', $recent, 'medium', 'documents', 'fa-file-lines')];
    }

    private function backupNotifications(): array
    {
        if (!$this->tableExists('backups')) return [];
        $failed = $this->count('backups b', 'UPPER(COALESCE(b.status,"")) NOT IN ("SUCCESS","RESTORED") AND ' . $this->tenantWhere('b', 'backups') . ' AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        return [$this->item('backup_failed', 'Sao luu that bai', 'Co ban sao luu/khôi phuc khong thanh cong', $failed, 'urgent', 'backups', 'fa-database')];
    }

    private function item(string $key, string $title, string $message, int $count, string $priority, string $screen, string $icon): array
    {
        return ['key' => $key, 'title' => $title, 'message' => $message, 'count' => $count, 'priority' => $priority, 'screen' => $screen, 'icon' => $icon, 'created_at' => date('c')];
    }

    private function count(string $from, string $where, array $params = []): int
    {
        return (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM $from WHERE $where", $this->withTenant($params)) ?: [])['total'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );
        return (int)($row['total'] ?? 0) > 0;
    }

    private function assertColumns(string $table, array $expected): void
    {
        $columns = $this->fetchAll('SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table', ['table' => $table]);
        if (!$columns) throw new \RuntimeException('Notification schema is not ready: missing table ' . $table);
        $actual = [];
        foreach ($columns as $column) $actual[(string) $column['name']] = $column;
        foreach ($expected as $name => [$type, $nullable]) {
            if (!isset($actual[$name])) throw new \RuntimeException('Notification schema is not ready: missing column ' . $table . '.' . $name);
            if (strtolower((string) $actual[$name]['type']) !== strtolower($type) || strtoupper((string) $actual[$name]['nullable']) !== $nullable) {
                throw new \RuntimeException('Notification schema is not ready: incompatible column ' . $table . '.' . $name);
            }
        }
    }

    private function assertIndex(string $table, string $index, array $columns): void
    {
        $rows = $this->fetchAll('SELECT COLUMN_NAME AS name FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:index ORDER BY SEQ_IN_INDEX', ['table' => $table, 'index' => $index]);
        $actual = array_map(fn($row) => (string) $row['name'], $rows);
        if ($actual !== $columns) throw new \RuntimeException('Notification schema is not ready: missing index ' . $table . '.' . $index);
    }

    private function states(int $userId, array $keys): array
    {
        if (!$keys) return [];
        $placeholders = [];
        $params = $this->withTenant(['user_id' => $userId]);
        foreach (array_values($keys) as $index => $key) {
            $name = 'k' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $key;
        }
        $rows = $this->fetchAll('SELECT notification_key, read_at, dismissed_at FROM notification_states WHERE user_id=:user_id AND ' . $this->tenantWhere('notification_states') . ' AND notification_key IN (' . implode(',', $placeholders) . ')', $params);
        $map = [];
        foreach ($rows as $row) $map[(string)$row['notification_key']] = $row;
        return $map;
    }
}
