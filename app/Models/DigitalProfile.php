<?php

namespace App\Models;

use App\Core\BaseModel;

final class DigitalProfile extends BaseModel
{
    public function household(int $id): ?array
    {
        $household = (new Household())->find($id);
        if (!$household) return null;

        $members = $this->fetchAll(
            'SELECT c.*, h.household_code, h.address AS household_address FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.household_id=:id AND c.status <> "DELETED" ORDER BY CASE WHEN c.relationship="Chủ hộ" THEN 0 ELSE 1 END, c.full_name',
            ['id' => $id]
        );
        $citizenIds = array_map(fn($row) => (int) $row['id'], $members);

        return [
            'type' => 'household',
            'profile' => $this->compactRow($household),
            'sections' => [
                'general' => $this->section($household, [
                    'household_code' => 'Mã hộ',
                    'head_citizen_name' => 'Chủ hộ',
                    'address' => 'Địa chỉ',
                    'phone' => 'Điện thoại',
                    'area_code' => 'Mã khu vực',
                    'latitude' => 'Vĩ độ GPS',
                    'longitude' => 'Kinh độ GPS',
                    'location_accuracy' => 'Độ chính xác GPS',
                    'location_source' => 'Nguồn định vị',
                    'location_updated_at' => 'Cập nhật vị trí',
                    'household_type' => 'Diện hộ',
                    'status' => 'Trạng thái',
                ]),
                'statistics' => $this->section($household, [
                    'member_count_real' => 'Tổng nhân khẩu',
                    'at_home_count' => 'Ở nhà',
                    'away_count' => 'Đi vắng',
                    'poor_household' => 'Hộ nghèo',
                    'near_poor_household' => 'Hộ cận nghèo',
                    'meritorious_family' => 'Gia đình có công',
                    'disabled_household' => 'Hộ có người khuyết tật',
                    'note' => 'Ghi chú hộ',
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
            'SELECT id, citizen_code, full_name, gender, date_of_birth, identity_number, relationship, residency_status, presence_status, life_status FROM citizens WHERE household_id=:household_id AND status <> "DELETED" ORDER BY CASE WHEN relationship="Chủ hộ" THEN 0 ELSE 1 END, full_name',
            ['household_id' => $householdId]
        ) : [];
        $citizen['computed_age'] = $this->age($citizen['date_of_birth'] ?? null);

        return [
            'type' => 'citizen',
            'profile' => $this->compactRow($citizen),
            'sections' => [
                'basic' => $this->section($citizen, [
                    'full_name' => 'Họ và tên',
                    'citizen_code' => 'Mã nhân khẩu',
                    'identity_number' => 'CCCD/Số định danh',
                    'gender' => 'Giới tính',
                    'date_of_birth' => 'Ngày sinh',
                    'computed_age' => 'Tuổi',
                    'phone' => 'Số điện thoại',
                ]),
                'residence' => $this->section($citizen, [
                    'household_code' => 'Mã hộ',
                    'relationship' => 'Quan hệ với chủ hộ',
                    'household_address' => 'Địa chỉ thường trú',
                    'current_address' => 'Địa chỉ hiện tại',
                    'residency_status' => 'Cư trú',
                    'presence_status' => 'Hiện tại',
                    'life_status' => 'Tình trạng',
                ]),
                'personal' => $this->section($citizen, [
                    'occupation' => 'Nghề nghiệp',
                    'workplace' => 'Nơi làm việc',
                    'ethnicity' => 'Dân tộc',
                    'religion' => 'Tôn giáo',
                    'education_level' => 'Trình độ học vấn',
                    'marital_status' => 'Tình trạng hôn nhân',
                    'nationality' => 'Quốc tịch',
                    'note' => 'Ghi chú nhân khẩu',
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
            $items[] = ['time' => $note['updated_at'] ?? $note['created_at'] ?? null, 'type' => 'NOTE', 'title' => $note['title'] ?? 'Ghi chú nghiệp vụ', 'description' => $note['content'] ?? '', 'data' => $note];
        }
        foreach ($this->logs($module, (string) $entityId) as $log) {
            $items[] = ['time' => $log['created_at'] ?? null, 'type' => 'LOG', 'title' => $log['message'] ?? $log['action'] ?? 'Nhật ký', 'description' => $log['actor_email'] ?? '', 'data' => $log];
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
        $title = trim((string) ($data['title'] ?? 'Ghi chú nghiệp vụ')) ?: 'Ghi chú nghiệp vụ';
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') throw new \RuntimeException('Nội dung ghi chú là bắt buộc');
        $section = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data['section'] ?? 'general'))) ?: 'general';
        $id = $this->insert('INSERT INTO profile_notes (module, entity_id, section, title, content, status, created_by) VALUES (:module,:entity_id,:section,:title,:content,"ACTIVE",:user)', [
            'module' => $module,
            'entity_id' => $entityId,
            'section' => $section,
            'title' => mb_substr($title, 0, 255),
            'content' => $content,
            'user' => $userId,
        ]);
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
        $this->execute('UPDATE profile_notes SET status="DELETED", deleted_at=NOW(), deleted_by=:user WHERE id=:id', ['id' => $id, 'user' => $userId]);
        return $note;
    }

    public function updateNote(int $id, array $data, int $userId): ?array
    {
        $this->assertNotesReady();
        $note = $this->noteById($id);
        if (!$note) return null;
        $title = trim((string) ($data['title'] ?? $note['title'] ?? 'Ghi chú nghiệp vụ')) ?: 'Ghi chú nghiệp vụ';
        $content = trim((string) ($data['content'] ?? $note['content'] ?? ''));
        if ($content === '') throw new \RuntimeException('Nội dung ghi chú là bắt buộc');
        $section = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($data['section'] ?? $note['section'] ?? 'general'))) ?: 'general';
        $this->execute('UPDATE profile_notes SET section=:section, title=:title, content=:content, updated_by=:user WHERE id=:id AND status="ACTIVE"', [
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
        return $this->fetchAll('SELECT m.*, c.full_name, c.citizen_code, c.identity_number, h.household_code FROM movements m LEFT JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id WHERE m.status <> "DELETED" AND (' . implode(' OR ', $parts) . ') ORDER BY m.effective_date DESC, m.id DESC', $params);
    }

    private function citizenMovements(int $citizenId): array
    {
        return $this->fetchAll('SELECT m.*, c.full_name, c.citizen_code, c.identity_number, h.household_code FROM movements m LEFT JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id WHERE m.citizen_id=:citizen_id AND m.status <> "DELETED" ORDER BY m.effective_date DESC, m.id DESC', ['citizen_id' => $citizenId]);
    }

    private function files(string $module, int $entityId): array
    {
        $description = $this->columnExists('file_attachments', 'description') ? 'description' : 'NULL AS description';
        $profileSection = $this->columnExists('file_attachments', 'profile_section') ? 'profile_section' : 'NULL AS profile_section';
        return $this->fetchAll('SELECT id, module, entity_id, file_type, original_name, file_path, mime_type, file_size, created_at, created_by, ' . $description . ', ' . $profileSection . ' FROM file_attachments WHERE module=:module AND entity_id=:entity_id AND status="ACTIVE" ORDER BY created_at DESC, id DESC', ['module' => $module, 'entity_id' => $entityId]);
    }

    private function notes(string $module, int $entityId): array
    {
        if (!$this->tableExists('profile_notes')) return [];
        return $this->fetchAll('SELECT n.*, u.display_name AS created_by_name, u.email AS created_by_email FROM profile_notes n LEFT JOIN users u ON u.id=n.created_by WHERE n.module=:module AND n.entity_id=:entity_id AND n.status="ACTIVE" ORDER BY n.created_at DESC, n.id DESC', ['module' => $module, 'entity_id' => $entityId]);
    }

    private function noteById(int $id): ?array
    {
        return $this->fetchOne('SELECT n.*, u.display_name AS created_by_name, u.email AS created_by_email FROM profile_notes n LEFT JOIN users u ON u.id=n.created_by WHERE n.id=:id AND n.status="ACTIVE"', ['id' => $id]);
    }

    private function logs(string $module, string $entityId): array
    {
        $columns = ['id', 'actor_user_id', 'actor_email', 'module', 'action', 'entity_id', 'level', 'message', 'metadata', 'created_at'];
        foreach (['ip_address', 'user_agent', 'before_data', 'after_data'] as $column) {
            if ($this->columnExists('audit_logs', $column)) $columns[] = $column;
        }
        return $this->fetchAll('SELECT ' . implode(',', $columns) . ' FROM audit_logs WHERE module=:module AND entity_id=:entity_id ORDER BY created_at DESC, id DESC LIMIT 100', ['module' => $module, 'entity_id' => $entityId]);
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
            'party_member' => 'Đảng viên', 'youth_union_member' => 'Đoàn viên Thanh niên', 'women_union_member' => 'Hội viên Hội Phụ nữ', 'farmers_union_member' => 'Hội viên Hội Nông dân', 'veterans_union_member' => 'Hội viên Hội Cựu chiến binh', 'elderly_union_member' => 'Hội viên Hội Người cao tuổi',
            'meritorious_person' => 'Người có công', 'martyr_relative' => 'Thân nhân liệt sĩ', 'wounded_soldier' => 'Thương binh', 'sick_soldier' => 'Bệnh binh', 'disabled_person' => 'Người khuyết tật', 'social_assistance' => 'Bảo trợ xã hội',
            'employed' => 'Có việc làm', 'unemployed' => 'Thất nghiệp', 'freelance_labor' => 'Lao động tự do', 'out_province_labor' => 'Lao động ngoài tỉnh', 'foreign_labor' => 'Lao động nước ngoài', 'pupil' => 'Học sinh', 'student' => 'Sinh viên', 'retired' => 'Nghỉ hưu',
        ];
    }

    private function movementLabel(string $type): string
    {
        return [
            'BIRTH' => 'Sinh ra', 'DEATH' => 'Tử', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi', 'HOUSEHOLD_SPLIT' => 'Tách hộ', 'HOUSEHOLD_MERGE' => 'Nhập hộ', 'HOUSEHOLD_HEAD_CHANGE' => 'Thay đổi chủ hộ', 'HEAD_CHANGE' => 'Thay đổi chủ hộ', 'CITIZEN_UPDATE' => 'Cập nhật thông tin', 'INFO_CHANGE' => 'Cập nhật thông tin', 'IDENTITY_UPDATE' => 'Thay đổi CCCD', 'MARRIAGE' => 'Kết hôn', 'RESTORE' => 'Hoàn tác', 'TEMPORARY_RESIDENCE' => 'Đăng ký tạm trú', 'TEMPORARY_ABSENCE' => 'Tạm vắng',
        ][$type] ?? 'Biến động khác';
    }

    private function fileSectionLabel(string $section, string $type): string
    {
        return [
            'front_house' => 'Ảnh mặt trước nhà', 'location_photo' => 'Ảnh vị trí thực tế', 'household_video' => 'Video hộ gia đình', 'household_document' => 'Tài liệu hộ gia đình',
            'portrait' => 'Ảnh chân dung', 'cccd_front' => 'CCCD mặt trước', 'cccd_back' => 'CCCD mặt sau', 'birth_certificate' => 'Giấy khai sinh', 'household_book' => 'Sổ hộ khẩu', 'citizen_document' => 'Giấy tờ liên quan',
        ][$section] ?? ($type === 'VIDEO' ? 'Video' : ($type === 'PHOTO' || $type === 'IMAGE' ? 'Hình ảnh' : 'Tệp đính kèm'));
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) return false;
        if (is_string($value) && trim($value) === '') return false;
        return true;
    }

    private function formatValue(mixed $value): mixed
    {
        if (is_bool($value)) return $value ? 'Có' : 'Không';
        if (is_numeric($value) && in_array((string) $value, ['0', '1'], true)) return ((int) $value) === 1 ? 'Có' : 'Không';
        return $value;
    }

    private function age(mixed $date): ?int
    {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $date)) return null;
        try { return (int) (new \DateTimeImmutable((string) $date))->diff(new \DateTimeImmutable('today'))->y; } catch (\Throwable) { return null; }
    }

    private function maskIdentity(string $identity): string
    {
        $identity = trim($identity);
        if (mb_strlen($identity) <= 8) return $identity;
        return mb_substr($identity, 0, 4) . '••••' . mb_substr($identity, -4);
    }

    private function normalizeModule(string $module): string
    {
        $module = $module === 'persons' ? 'citizen' : rtrim($module, 's');
        if (!in_array($module, ['household', 'citizen'], true)) throw new \RuntimeException('Loại hồ sơ không hợp lệ');
        return $module;
    }

    private function assertNotesReady(): void
    {
        if (!$this->tableExists('profile_notes')) throw new \RuntimeException('Bảng ghi chú hồ sơ chưa sẵn sàng');
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }
}
