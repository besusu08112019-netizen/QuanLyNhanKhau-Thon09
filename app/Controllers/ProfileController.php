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
        $module = $this->normalizeModule($module);
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'read');
        $profile = $module === 'citizen'
            ? $this->profiles->citizen((int) $id)
            : $this->profiles->household((int) $id);
        $profile ? $this->ok($profile['timeline'] ?? []) : $this->fail('Không tìm thấy hồ sơ', 404);
    }

    public function createNote(string $module, string $id): void
    {
        $module = $this->normalizeModule($module);
        $user = $this->requirePermission('profile', 'create');
        $this->requireProfileSourcePermission($module);
        $note = $this->profiles->createNote($module, (int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, $module, 'note', 'Thêm ghi chú hồ sơ', (int) $id, ['note' => $note['id'] ?? null, 'section' => $note['section'] ?? null]);
        $this->ok($note);
    }

    public function deleteNote(string $id): void
    {
        $note = $this->profiles->note((int) $id);
        if (!$note) $this->fail('Không tìm thấy ghi chú hồ sơ', 404);
        $module = $this->normalizeModule((string) ($note['module'] ?? 'household'));
        $user = $this->requirePermission('profile', 'delete');
        $this->requireProfileSourcePermission($module);
        $this->profiles->deleteNote((int) $id, (int) $user['id']);
        $this->audit($user, $module, 'delete_note', 'Xóa ghi chú hồ sơ', $note['entity_id'] ?? null, ['note' => (int) $id, 'title' => $note['title'] ?? '']);
        $this->ok(['id' => (int) $id]);
    }

    public function updateNote(string $id): void
    {
        $note = $this->profiles->note((int) $id);
        if (!$note) $this->fail('Không tìm thấy ghi chú hồ sơ', 404);
        $module = $this->normalizeModule((string) ($note['module'] ?? 'household'));
        $user = $this->requirePermission('profile', 'update');
        $this->requireProfileSourcePermission($module);
        $updated = $this->profiles->updateNote((int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, $module, 'update_note', 'Sửa ghi chú hồ sơ', $note['entity_id'] ?? null, ['note' => (int) $id, 'title' => $updated['title'] ?? '']);
        $this->ok($updated);
    }

    private function normalizeModule(string $module): string
    {
        $module = $module === 'persons' ? 'citizen' : rtrim($module, 's');
        if (!in_array($module, ['household', 'citizen'], true)) {
            $this->fail('Loại hồ sơ không hợp lệ');
        }
        return $module;
    }

    private function requireProfileSourcePermission(string $module): void
    {
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'update');
    }
}
