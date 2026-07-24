<?php

namespace App\Models;

use App\Core\BaseModel;

final class NotificationCenter extends BaseModel
{
    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS notification_states (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_key VARCHAR(160) NOT NULL,
  read_at DATETIME NULL,
  dismissed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_notification_state_user_key (user_id, notification_key),
  KEY idx_notification_states_user_read (user_id, read_at),
  KEY idx_notification_states_user_dismissed (user_id, dismissed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
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
        $this->execute('INSERT INTO notification_states (user_id, notification_key, read_at) VALUES (:user_id,:notification_key,NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at, NOW()), dismissed_at=NULL', ['user_id' => $userId, 'notification_key' => $key]);
    }

    public function dismiss(int $userId, string $key): void
    {
        $this->ensureSchema();
        $this->execute('INSERT INTO notification_states (user_id, notification_key, read_at, dismissed_at) VALUES (:user_id,:notification_key,NOW(),NOW()) ON DUPLICATE KEY UPDATE read_at=COALESCE(read_at, NOW()), dismissed_at=NOW()', ['user_id' => $userId, 'notification_key' => $key]);
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
        $new = $this->count('complaints c', 'c.soft_status <> "DELETED" AND c.closed_at IS NULL AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $overdue = $this->count('complaints c', 'c.soft_status <> "DELETED" AND c.closed_at IS NULL AND c.due_at IS NOT NULL AND c.due_at < NOW()');
        return [
            $this->item('complaints_new', 'Phan anh moi', 'Co phan anh moi can tiep nhan/xu ly', $new, 'high', 'complaints', 'fa-comments'),
            $this->item('complaints_overdue', 'Phan anh qua han', 'Co phan anh da qua han xu ly', $overdue, 'urgent', 'complaints', 'fa-triangle-exclamation'),
        ];
    }

    private function workTaskNotifications(): array
    {
        if (!$this->tableExists('work_tasks')) return [];
        $new = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND wt.completed_at IS NULL AND wt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $due = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND wt.completed_at IS NULL AND wt.due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)');
        $overdue = $this->count('work_tasks wt', 'wt.soft_status <> "DELETED" AND wt.completed_at IS NULL AND wt.due_at IS NOT NULL AND wt.due_at < NOW()');
        return [
            $this->item('work_tasks_new', 'Cong viec moi', 'Co cong viec moi duoc tao', $new, 'medium', 'workTasks', 'fa-list-check'),
            $this->item('work_tasks_due_soon', 'Cong viec gan den han', 'Cong viec se den han trong 3 ngay', $due, 'high', 'workTasks', 'fa-clock'),
            $this->item('work_tasks_overdue', 'Cong viec qua han', 'Co cong viec da qua han', $overdue, 'urgent', 'workTasks', 'fa-triangle-exclamation'),
        ];
    }

    private function calendarNotifications(): array
    {
        if (!$this->tableExists('calendar_events')) return [];
        $today = $this->count('calendar_events ce', 'ce.soft_status <> "DELETED" AND ce.status="SCHEDULED" AND DATE(ce.start_at)=CURDATE()');
        $upcoming = $this->count('calendar_events ce', 'ce.soft_status <> "DELETED" AND ce.status="SCHEDULED" AND ce.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)');
        return [
            $this->item('calendar_today', 'Lich hom nay', 'Co lich cong tac trong ngay', $today, 'high', 'workCalendar', 'fa-calendar-day'),
            $this->item('calendar_upcoming', 'Lich sap toi', 'Co lich cong tac trong 3 ngay toi', $upcoming, 'medium', 'workCalendar', 'fa-calendar-days'),
        ];
    }

    private function documentNotifications(): array
    {
        if (!$this->tableExists('village_documents')) return [];
        $recent = $this->count('village_documents vd', 'vd.status <> "DELETED" AND vd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        return [$this->item('documents_recent', 'Van ban moi', 'Co van ban moi duoc cap nhat', $recent, 'medium', 'documents', 'fa-file-lines')];
    }

    private function backupNotifications(): array
    {
        if (!$this->tableExists('backups')) return [];
        $failed = $this->count('backups b', 'UPPER(COALESCE(b.status,"")) NOT IN ("SUCCESS","RESTORED") AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
        return [$this->item('backup_failed', 'Sao luu that bai', 'Co ban sao luu/khôi phuc khong thanh cong', $failed, 'urgent', 'backups', 'fa-database')];
    }

    private function item(string $key, string $title, string $message, int $count, string $priority, string $screen, string $icon): array
    {
        return ['key' => $key, 'title' => $title, 'message' => $message, 'count' => $count, 'priority' => $priority, 'screen' => $screen, 'icon' => $icon, 'created_at' => date('c')];
    }

    private function count(string $from, string $where, array $params = []): int
    {
        return (int)(($this->fetchOne("SELECT COUNT(*) AS total FROM $from WHERE $where", $params) ?: [])['total'] ?? 0);
    }

    private function states(int $userId, array $keys): array
    {
        if (!$keys) return [];
        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach (array_values($keys) as $index => $key) {
            $name = 'k' . $index;
            $placeholders[] = ':' . $name;
            $params[$name] = $key;
        }
        $rows = $this->fetchAll('SELECT notification_key, read_at, dismissed_at FROM notification_states WHERE user_id=:user_id AND notification_key IN (' . implode(',', $placeholders) . ')', $params);
        $map = [];
        foreach ($rows as $row) $map[(string)$row['notification_key']] = $row;
        return $map;
    }
}
