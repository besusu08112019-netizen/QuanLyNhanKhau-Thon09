<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Policies\AgePolicy;

final class DigitalProfile extends BaseModel
{
    public function household(int $id): ?array
    {
        $household = (new Household())->find($id);
        if (!$household) return null;

        $members = $this->fetchAll(
            'SELECT c.*, h.household_code, h.address AS household_address FROM citizens c INNER JOIN households h ON h.id=c.household_id AND ' . $this->tenantWhere('h', 'households') . ' WHERE c.household_id=:id AND c.status <> "DELETED" AND ' . $this->tenantWhere('c', 'citizens') . ' ORDER BY CASE WHEN c.relationship="Chá»§ há»™" THEN 0 ELSE 1 END, c.full_name',
            ['id' => $id]
        );
        $citizenIds = array_map(fn($row) => (int) $row['id'], $members);

        return [
            'type' => 'household',
            'profile' => $this->compactRow($household),
            'sections' => [
                'general' => $this->section($household, [
                    'household_code' => 'MÃ£ há»™',
                    'head_citizen_name' => 'Chá»§ há»™',
                    'address' => 'Äá»‹a chá»‰',
                    'phone' => 'Äiá»‡n thoáº¡i',
                    'area_code' => 'MÃ£ khu vá»±c',
                    'latitude' => 'VÄ© Ä‘á»™ GPS',
                    'longitude' => 'Kinh Ä‘á»™ GPS',
                    'location_accuracy' => 'Äá»™ chÃ­nh xÃ¡c GPS',
                    'location_source' => 'Nguá»“n Ä‘á»‹nh vá»‹',
                    'location_updated_at' => 'Cáº­p nháº­t vá»‹ trÃ­',
                    'household_type' => 'Diá»‡n há»™',
                    'status' => 'Tráº¡ng thÃ¡i',
                ]),
                'statistics' => $this->section($household, [
                    'member_count_real' => 'Tá»•ng nhÃ¢n kháº©u',
                    'at_home_count' => 'á»ž nhÃ ',
                    'away_count' => 'Äi váº¯ng',
                    'meritorious_policy' => 'Há»™ cÃ³ cÃ´ng',
                    'disabled_policy' => 'Há»™ cÃ³ ngÆ°á»i khuyáº¿t táº­t',
                    'poor_household' => 'Há»™ nghÃ¨o',
                    'near_poor_household' => 'Há»™ cáº­n nghÃ¨o',
                    'note' => 'Ghi chÃº há»™',
                ]),
            ],
            'members' => array_map(fn($row) => $this->citizenSummary($row), $members),
            'files' => $this->files('household', $id),
            'notes' => $this->notes('household', $id),
            'movements' => $this->householdMovements($id, $citizenIds),
            'logs' => $this->logs('household', (string) $id),
            'timeline' => $this->timeline('household', $id, $citizenIds),
            'links' => $this->householdLinks($id),
        ];
    }

    public function citizen(int $id): ?array
    {
        $citizen = (new Citizen())->find($id);
        if (!$citizen) return null;

        $householdId = (int) ($citizen['household_id'] ?? 0);
        $household = $householdId > 0 ? (new Household())->find($householdId) : null;
        $family = $householdId > 0 ? $this->fetchAll(
            'SELECT id, citizen_code, full_name, gender, date_of_birth, identity_number, relationship, residency_status, presence_status, life_status FROM citizens WHERE household_id=:household_id AND status <> "DELETED" AND ' . $this->tenantWhere('citizens') . ' ORDER BY CASE WHEN relationship="Chá»§ há»™" THEN 0 ELSE 1 END, full_name',
            ['household_id' => $householdId]
        ) : [];
        $citizen['computed_age'] = $this->age($citizen['date_of_birth'] ?? null);

        return [
            'type' => 'citizen',
            'profile' => $this->compactRow($citizen),
            'sections' => [
                'basic' => $this->section($citizen, [
                    'full_name' => 'Há» vÃ  tÃªn',
                    'citizen_code' => 'MÃ£ nhÃ¢n kháº©u',
                    'identity_number' => 'CCCD/Sá»‘ Ä‘á»‹nh danh',
                    'gender' => 'Giá»›i tÃ­nh',
                    'date_of_birth' => 'NgÃ y sinh',
                    'computed_age' => 'Tuá»•i',
                    'phone' => 'Sá»‘ Ä‘iá»‡n thoáº¡i',
                ]),
                'residence' => $this->section($citizen, [
                    'household_code' => 'MÃ£ há»™',
                    'relationship' => 'Quan há»‡ vá»›i chá»§ há»™',
                    'head_citizen_name' => 'Chá»§ há»™',
                    'father_display_name' => 'Há» tÃªn bá»‘',
                    'mother_display_name' => 'Há» tÃªn máº¹',
                    'household_address' => 'Äá»‹a chá»‰ thÆ°á»ng trÃº',
                    'current_address' => 'Äá»‹a chá»‰ hiá»‡n táº¡i',
                    'residency_status' => 'CÆ° trÃº',
                    'presence_status' => 'Hiá»‡n táº¡i',
                    'life_status' => 'TÃ¬nh tráº¡ng',
                ]),
                'personal' => $this->section($citizen, [
                    'occupation' => 'Nghá» nghiá»‡p',
                    'workplace' => 'NÆ¡i lÃ m viá»‡c',
                    'ethnicity' => 'DÃ¢n tá»™c',
                    'religion' => 'TÃ´n giÃ¡o',
                    'education_level' => 'TrÃ¬nh Ä‘á»™ há»c váº¥n',
                    'marital_status' => 'TÃ¬nh tráº¡ng hÃ´n nhÃ¢n',
                    'nationality' => 'Quá»‘c tá»‹ch',
                    'note' => 'Ghi chÃº nhÃ¢n kháº©u',
                ]),
                'administrative' => $this->section($citizen, $this->extendedCitizenLabels()),
            ],
            'household' => $household ? $this->compactRow($household) : null,
            'family' => array_map(fn($row) => $this->citizenSummary($row), $family),
            'files' => $this->files('citizen', $id),
            'notes' => $this->notes('citizen', $id),
            'movements' => $this->citizenMovements($id),
            'logs' => $this->logs('citizen', (string) $id),
            'timeline' => $this->timeline('citizen', $id),
            'links' => $this->citizenLinks($id, $householdId),
        ];
    }

    public function timeline(string $module, int $entityId, array $citizenIds = []): array
    {
        $items = [];
        foreach ($this->files($module, $entityId) as $file) {
            $items[] = ['time' => $file['created_at'] ?? null, 'type' => 'FILE', 'title' => $this->fileSectionLabel((string) ($file['profile_section'] ?? ''), (string) ($file['file_type'] ?? '')), 'description' => $file['original_name'] ?? '', 'data' => $file];
        }
        foreach ($this->notes($module, $entityId) as $note) {
            $items[] = ['time' => $note['updated_at'] ?? $note['created_at'] ?? null, 'type' => 'NOTE', 'title' => $note['title'] ?? 'Ghi chÃº nghiá»‡p vá»¥', 'description' => $note['content'] ?? '', 'data' => $note];
        }
        foreach ($this->logs($module, (string) $entityId) as $log) {
            $items[] = ['time' => $log['created_at'] ?? null, 'type' => 'LOG', 'title' => $log['message'] ?? $log['action'] ?? 'Nháº­t kÃ½', 'description' => $log['actor_email'] ?? '', 'data' => $log];
        }
        $movements = $module === 'household' ? $this->householdMovements($entityId, $citizenIds) : $this->citizenMovements($entityId);
        foreach ($movements as $movement) {
            $items[] = ['time' => $movement['effective_date'] ?? $movement['created_at'] ?? null, 'type' => 'MOVEMENT', 'title' => $this->movementLabel((string) ($movement['type'] ?? 'OTHER')), 'description' => trim((string) (($movement['full_name'] ?? '') . ' ' . ($movement['reason'] ?? ''))), 'data' => $movement];
        }
        usort($items, fn($a, $b) => strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? '')));
        return array_values($items);
    }

    public function createNote(string $module, int $entityId, array $data, int $userId): array
    {
        $this->assertNotesReady();
        $module = $this->normalizeModule($module);
        $title = trim((string) ($data['title'] ?? 'Ghi chÃº nghiá»‡p vá»¥')) ?: 'Ghi chÃº nghiá»‡p vá»¥';
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') throw new \RuntimeException('Ná»™i dung ghi chÃº lÃ  báº¯t buá»™c');
        $section = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data['section'] ?? 'general'))) ?: 'general';
        $columns = ['module', 'entity_id', 'section', 'title', 'content', 'status', 'created_by'];
        $params = [
            'module' => $module,
            'entity_id' => $entityId,
            'section' => $section,
            'title' => mb_substr($title, 0, 255),
            'content' => $content,
            'user' => $userId,
        ];
        $this->addTenantInsert('profile_notes', $columns, $params);
        $id = $this->insert('INSERT INTO profile_notes (' . implode(',', $columns) . ') VALUES (:module,:entity_id,:section,:title,:content,"ACTIVE",:user' . (in_array('village_id', $columns, true) ? ',:village_id' : '') . ')', $params);
        return $this->noteById($id) ?? ['id' => $id, 'module' => $module, 'entity_id' => $entityId, 'section' => $section, 'title' => $title, 'content' => $content];
    }

    public function note(int $id): ?array
    {
        return $this->tableExists('profile_notes') ? $this->noteById($id) : null;
    }

    public function deleteNote(int $id, int $userId): ?array
    {
        $this->assertNotesReady();
        $note = $this->noteById($id);
        if (!$note) return null;
        $this->execute('UPDATE profile_notes SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id AND ' . $this->tenantWhere('profile_notes'), ['id' => $id, 'user' => $userId]);
        return $note;
    }

    public function updateNote(int $id, array $data, int $userId): ?array
    {
        $this->assertNotesReady();
        $note = $this->noteById($id);
        if (!$note) return null;
        $title = trim((string) ($data['title'] ?? $note['title'] ?? 'Ghi chÃº nghiá»‡p vá»¥')) ?: 'Ghi chÃº nghiá»‡p vá»¥';
        $content = trim((string) ($data['content'] ?? $note['content'] ?? ''));
        if ($content === '') throw new \RuntimeException('Ná»™i dung ghi chÃº lÃ  báº¯t buá»™c');
        $section = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data['section'] ?? $note['section'] ?? 'general'))) ?: 'general';
        $this->execute('UPDATE profile_notes SET section=:section, title=:title, content=:content, updated_by=:user WHERE id=:id AND status="ACTIVE" AND ' . $this->tenantWhere('profile_notes'), [
            'id' => $id,
            'section' => $section,
            'title' => mb_substr($title, 0, 255),
            'content' => $content,
            'user' => $userId,
        ]);
        return $this->noteById($id);
    }

    private function householdMovements(int $householdId, array $citizenIds): array
    {
        $params = ['household_id' => $householdId];
        $parts = ['m.household_id = :household_id'];
        if ($citizenIds) {
            $in = [];
            foreach ($citizenIds as $index => $citizenId) {
                $key = 'citizen_' . $index;
                $in[] = ':' . $key;
                $params[$key] = $citizenId;
            }
            $parts[] = 'm.citizen_id IN (' . implode(',', $in) . ')';
        }
        return $this->fetchAll('SELECT m.*, c.full_name, c.citizen_code, c.identity_number, h.household_code FROM movements m LEFT JOIN citizens c ON c.id=m.citizen_id AND ' . $this->tenantWhere('c', 'citizens') . ' LEFT JOIN households h ON h.id=m.household_id AND ' . $this->tenantWhere('h', 'households') . ' WHERE m.status <> "DELETED" AND ' . $this->tenantWhere('m', 'movements') . ' AND (' . implode(' OR ', $parts) . ') ORDER BY m.effective_date DESC, m.id DESC', $params);
    }

    private function citizenMovements(int $citizenId): array
    {
        return $this->fetchAll('SELECT m.*, c.full_name, c.citizen_code, c.identity_number, h.household_code FROM movements m LEFT JOIN citizens c ON c.id=m.citizen_id AND ' . $this->tenantWhere('c', 'citizens') . ' LEFT JOIN households h ON h.id=m.household_id AND ' . $this->tenantWhere('h', 'households') . ' WHERE m.citizen_id=:citizen_id AND ' . $this->tenantWhere('m', 'movements') . ' AND m.status <> "DELETED" ORDER BY m.effective_date DESC, m.id DESC', ['citizen_id' => $citizenId]);
    }

    private function files(string $module, int $entityId): array
    {
        if (!$this->tableExists('file_attachments')) return [];
        return array_map(
            fn(array $row): array => (new FileAttachment())->normalizeRow($row),
            (new FileAttachment())->byEntity($module, $entityId)
        );
    }

    private function notes(string $module, int $entityId): array
    {
        if (!$this->tableExists('profile_notes')) return [];
        return $this->fetchAll('SELECT n.*, u.display_name AS created_by_name, u.email AS created_by_email FROM profile_notes n LEFT JOIN users u ON u.id=n.created_by WHERE n.module=:module AND n.entity_id=:entity_id AND n.status="ACTIVE" AND ' . $this->tenantWhere('n', 'profile_notes') . ' ORDER BY n.created_at DESC, n.id DESC', ['module' => $module, 'entity_id' => $entityId]);
    }

    private function noteById(int $id): ?array
    {
        return $this->fetchOne('SELECT n.*, u.display_name AS created_by_name, u.email AS created_by_email FROM profile_notes n LEFT JOIN users u ON u.id=n.created_by WHERE n.id=:id AND n.status="ACTIVE" AND ' . $this->tenantWhere('n', 'profile_notes'), ['id' => $id]);
    }

    private function logs(string $module, string $entityId): array
    {
        $columns = ['id', 'actor_user_id', 'actor_email', 'module', 'action', 'entity_id', 'level', 'message', 'metadata', 'created_at'];
        foreach (['ip_address', 'user_agent', 'before_data', 'after_data'] as $column) {
            if ($this->columnExists('audit_logs', $column)) $columns[] = $column;
        }
        return $this->fetchAll('SELECT ' . implode(',', $columns) . ' FROM audit_logs WHERE module=:module AND entity_id=:entity_id AND ' . $this->tenantWhere('audit_logs') . ' ORDER BY created_at DESC, id DESC LIMIT 100', ['module' => $module, 'entity_id' => $entityId]);
    }

    private function section(array $row, array $labels): array
    {
        $items = [];
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $row) || !$this->hasValue($row[$key])) continue;
            $items[] = ['key' => $key, 'label' => $label, 'value' => $this->formatValue($row[$key])];
        }
        return $items;
    }

    private function compactRow(array $row): array
    {
        return array_filter($row, fn($value) => $this->hasValue($value));
    }

    private function citizenSummary(array $row): array
    {
        $row['computed_age'] = $this->age($row['date_of_birth'] ?? null);
        if (!empty($row['identity_number'])) $row['identity_masked'] = $this->maskIdentity((string) $row['identity_number']);
        return $this->compactRow($row);
    }

    private function householdLinks(int $id): array
    {
        return [
            'gis' => ['screen' => 'gis', 'entity' => 'household', 'id' => $id],
            'members' => ['screen' => 'persons', 'householdId' => $id],
            'files' => ['api' => '/api/files?module=household&entityId=' . $id],
        ];
    }

    private function citizenLinks(int $id, int $householdId): array
    {
        return [
            'household' => $householdId > 0 ? ['api' => '/api/profiles/household/' . $householdId, 'id' => $householdId] : null,
            'movements' => ['screen' => 'movements', 'citizenId' => $id],
            'files' => ['api' => '/api/files?module=citizen&entityId=' . $id],
        ];
    }

    private function extendedCitizenLabels(): array
    {
        return [
            'party_member' => 'Äáº£ng viÃªn', 'youth_union_member' => 'ÄoÃ n viÃªn Thanh niÃªn', 'women_union_member' => 'Há»™i viÃªn Há»™i Phá»¥ ná»¯', 'farmers_union_member' => 'Há»™i viÃªn Há»™i NÃ´ng dÃ¢n', 'veterans_union_member' => 'Há»™i viÃªn Há»™i Cá»±u chiáº¿n binh', 'elderly_union_member' => 'Há»™i viÃªn Há»™i NgÆ°á»i cao tuá»•i',
            'martyr_relative' => 'ThÃ¢n nhÃ¢n liá»‡t sÄ©', 'wounded_soldier' => 'ThÆ°Æ¡ng binh', 'sick_soldier' => 'Bá»‡nh binh', 'chemical_warfare_victim' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ nhiá»…m cháº¥t Ä‘á»™c hÃ³a há»c', 'imprisoned_resistance_activist' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ Ä‘á»‹ch báº¯t tÃ¹, Ä‘Ã y', 'youth_volunteer' => 'Thanh niÃªn xung phong', 'resistance_hero' => 'Anh hÃ¹ng LLVTND / Anh hÃ¹ng Lao Ä‘á»™ng thá»i ká»³ khÃ¡ng chiáº¿n', 'revolutionary_activist' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng cÃ¡ch máº¡ng', 'disabled_person' => 'NgÆ°á»i khuyáº¿t táº­t', 'social_assistance' => 'Äang hÆ°á»Ÿng trá»£ cáº¥p xÃ£ há»™i',
            'employed' => 'CÃ³ viá»‡c lÃ m', 'unemployed' => 'Tháº¥t nghiá»‡p', 'freelance_labor' => 'Lao Ä‘á»™ng tá»± do', 'out_province_labor' => 'Lao Ä‘á»™ng ngoÃ i tá»‰nh', 'foreign_labor' => 'Lao Ä‘á»™ng nÆ°á»›c ngoÃ i', 'pupil' => 'Há»c sinh', 'student' => 'Sinh viÃªn', 'retired' => 'Nghá»‰ hÆ°u',
        ];
    }

    private function movementLabel(string $type): string
    {
        return [
            'BIRTH' => 'Sinh ra', 'DEATH' => 'Tá»­', 'MOVE_IN' => 'Chuyá»ƒn Ä‘áº¿n', 'MOVE_OUT' => 'Chuyá»ƒn Ä‘i', 'HOUSEHOLD_SPLIT' => 'TÃ¡ch há»™', 'HOUSEHOLD_MERGE' => 'Nháº­p há»™', 'HOUSEHOLD_HEAD_CHANGE' => 'Thay Ä‘á»•i chá»§ há»™', 'HEAD_CHANGE' => 'Thay Ä‘á»•i chá»§ há»™', 'CITIZEN_UPDATE' => 'Cáº­p nháº­t thÃ´ng tin', 'INFO_CHANGE' => 'Cáº­p nháº­t thÃ´ng tin', 'IDENTITY_UPDATE' => 'Thay Ä‘á»•i CCCD', 'MARRIAGE' => 'Káº¿t hÃ´n', 'RESTORE' => 'HoÃ n tÃ¡c', 'TEMPORARY_RESIDENCE' => 'ÄÄƒng kÃ½ táº¡m trÃº', 'TEMPORARY_ABSENCE' => 'Táº¡m váº¯ng',
        ][$type] ?? 'Biáº¿n Ä‘á»™ng khÃ¡c';
    }

    private function fileSectionLabel(string $section, string $type): string
    {
        return [
            'front_house' => 'Anh mat tien nha', 'inside_house' => 'Anh ben trong nha', 'auxiliary_work' => 'Anh cong trinh phu', 'household_video' => 'Video', 'household_pdf' => 'Tai lieu PDF', 'household_word' => 'File Word', 'household_excel' => 'File Excel', 'land_use_rights' => 'Ho so quyen su dung dat', 'building_permit' => 'Giay phep xay dung', 'electric_contract' => 'Hop dong dien', 'water_contract' => 'Hop dong nuoc', 'internet_contract' => 'Hop dong Internet', 'meeting_minutes' => 'Bien ban hop', 'household_document' => 'Cac giay to khac',
            'portrait' => 'áº¢nh chÃ¢n dung', 'cccd_front' => 'CCCD máº·t trÆ°á»›c', 'cccd_back' => 'CCCD máº·t sau', 'birth_certificate' => 'Giáº¥y khai sinh', 'household_book' => 'Sá»• há»™ kháº©u', 'citizen_document' => 'Giáº¥y tá» liÃªn quan',
        ][$section] ?? ($type === 'VIDEO' ? 'Video' : ($type === 'PHOTO' || $type === 'IMAGE' ? 'HÃ¬nh áº£nh' : 'Tá»‡p Ä‘Ã­nh kÃ¨m'));
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) return false;
        if (!is_string($value)) return true;
        $text = trim($value);
        if ($text === '') return false;
        $normalized = mb_strtolower($text, 'UTF-8');
        $normalized = strtr($normalized, ['Ä‘' => 'd', 'Ä' => 'd']);
        return !in_array($normalized, ['null', 'undefined', 'khÃ´ng cÃ³', 'khong co', 'n/a', 'na', '--'], true);
    }

    private function formatValue(mixed $value): mixed
    {
        if (is_bool($value)) return $value ? 'CÃ³' : 'KhÃ´ng';
        if (is_numeric($value) && in_array((string) $value, ['0', '1'], true)) return ((int) $value) === 1 ? 'CÃ³' : 'KhÃ´ng';
        return $value;
    }

    private function age(mixed $date): ?int
    {
        return AgePolicy::ageFromDate(is_scalar($date) ? (string) $date : null);
    }

    private function maskIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (mb_strlen($identity) <= 8) return $identity;
        return mb_substr($identity, 0, 4) . 'â€¢â€¢â€¢â€¢' . mb_substr($identity, -4);
    }

    private function normalizeModule(string $module): string
    {
        $module = $module === 'persons' ? 'citizen' : rtrim($module, 's');
        if (!in_array($module, ['household', 'citizen'], true)) throw new \RuntimeException('Loáº¡i há»“ sÆ¡ khÃ´ng há»£p lá»‡');
        return $module;
    }

    private function assertNotesReady(): void
    {
        if (!$this->tableExists('profile_notes')) throw new \RuntimeException('Báº£ng ghi chÃº há»“ sÆ¡ chÆ°a sáºµn sÃ ng');
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}

