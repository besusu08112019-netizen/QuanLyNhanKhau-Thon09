<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\NotificationCenter;

final class NotificationController extends BaseController
{
    private NotificationCenter $notifications;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->notifications = new NotificationCenter();
    }

    public function index(): void
    {
        $user = $this->requirePermission('notification', 'read');
        $this->ok($this->notifications->list((int)$user['id'], $this->query()));
    }

    public function read(string $key): void
    {
        $user = $this->requirePermission('notification', 'update');
        $this->notifications->markRead((int)$user['id'], $key);
        $this->audit($user, 'notification', 'read', 'Doc thong bao', null, ['key' => $key]);
        $this->ok(['key' => $key]);
    }

    public function dismiss(string $key): void
    {
        $user = $this->requirePermission('notification', 'update');
        $this->notifications->dismiss((int)$user['id'], $key);
        $this->audit($user, 'notification', 'dismiss', 'An thong bao', null, ['key' => $key]);
        $this->ok(['key' => $key]);
    }

    public function readAll(): void
    {
        $user = $this->requirePermission('notification', 'update');
        $this->notifications->markAllRead((int)$user['id']);
        $this->audit($user, 'notification', 'read_all', 'Doc tat ca thong bao');
        $this->ok(['read_all' => true]);
    }
}
