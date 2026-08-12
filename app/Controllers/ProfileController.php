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
        $profile ? $this->ok($profile) : $this->fail('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ há»™ gia Ä‘Ã¬nh', 404);
    }

    public function citizen(string $id): void
    {
        $this->requirePermission('citizen', 'read');
        $profile = $this->profiles->citizen((int) $id);
        $profile ? $this->ok($profile) : $this->fail('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ nhÃ¢n kháº©u', 404);
    }

    public function timeline(string $module, string $id): void
    {
        $module = $this->normalizeModule($module);
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'read');
        $profile = $module === 'citizen'
            ? $this->profiles->citizen((int) $id)
            : $this->profiles->household((int) $id);
        $profile ? $this->ok($profile['timeline'] ?? []) : $this->fail('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡', 404);
    }

    public function createNote(string $module, string $id): void
    {
        $module = $this->normalizeModule($module);
        $user = $this->requirePermission('profile', 'create');
        $this->requireProfileSourcePermission($module);
        $note = $this->profiles->createNote($module, (int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, $module, 'note', 'ThÃªm ghi chÃº há»“ sÆ¡', (int) $id, ['note' => $note['id'] ?? null, 'section' => $note['section'] ?? null]);
        $this->ok($note);
    }

    public function deleteNote(string $id): void
    {
        $note = $this->profiles->note((int) $id);
        if (!$note) $this->fail('KhÃ´ng tÃ¬m tháº¥y ghi chÃº há»“ sÆ¡', 404);
        $module = $this->normalizeModule((string) ($note['module'] ?? 'household'));
        $user = $this->requirePermission('profile', 'delete');
        $this->requireProfileSourcePermission($module);
        $this->profiles->deleteNote((int) $id, (int) $user['id']);
        $this->audit($user, $module, 'delete_note', 'XÃ³a ghi chÃº há»“ sÆ¡', $note['entity_id'] ?? null, ['note' => (int) $id, 'title' => $note['title'] ?? '']);
        $this->ok(['id' => (int) $id]);
    }

    public function updateNote(string $id): void
    {
        $note = $this->profiles->note((int) $id);
        if (!$note) $this->fail('KhÃ´ng tÃ¬m tháº¥y ghi chÃº há»“ sÆ¡', 404);
        $module = $this->normalizeModule((string) ($note['module'] ?? 'household'));
        $user = $this->requirePermission('profile', 'update');
        $this->requireProfileSourcePermission($module);
        $updated = $this->profiles->updateNote((int) $id, $this->input(), (int) $user['id']);
        $this->audit($user, $module, 'update_note', 'Sá»­a ghi chÃº há»“ sÆ¡', $note['entity_id'] ?? null, ['note' => (int) $id, 'title' => $updated['title'] ?? '']);
        $this->ok($updated);
    }

    private function normalizeModule(string $module): string
    {
        $module = $module === 'persons' ? 'citizen' : rtrim($module, 's');
        if (!in_array($module, ['household', 'citizen'], true)) {
            $this->fail('Loáº¡i há»“ sÆ¡ khÃ´ng há»£p lá»‡');
        }
        return $module;
    }

    private function requireProfileSourcePermission(string $module): void
    {
        $this->requirePermission($module === 'citizen' ? 'citizen' : 'household', 'update');
    }
}
