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
                ]),
            ],
            'members' => array_map(fn($row) => $this->citizenSummary($row), $members),
            'files' => $this->files('household', $id),
            'movements' => $this->householdMovements($id, $citizenIds),
            'logs' => $this->logs('household', (string) $id),
            'timeline' => $this->timeline('household', $id, $citizenIds),
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
                ]),
                'administrative' => $this->section($citizen, $this->extendedCitizenLabels()),
            ],
            'household' => $household ? $this->compactRow($household) : null,
            'family' => array_map(fn($row) => $this->citizenSummary($row), $family),
            'files' => $this->files('citizen', $id),
            'movements' => $this->citizenMovements($id),
            'logs' => $this->logs('citizen', (string) $id),
            'timeline' => $this->timeline('citizen', $id),
        ];
    }

    public function timeline(string $module, int $entityId, array $citizenIds = []): array
    {
        $items = [];
        foreach ($this->files($module, $entityId) as $file) {
            $items[] = ['time' => $file['created_at'] ?? null, 'type' => 'FILE', 'title' => 'Tệp đính kèm', 'description' => $file['original_name'] ?? '', 'data' => $file];
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
        return $this->fetchAll('SELECT id, module, entity_id, file_type, original_name, file_path, mime_type, file_size, created_at, created_by FROM file_attachments WHERE module=:module AND entity_id=:entity_id AND status="ACTIVE" ORDER BY created_at DESC, id DESC', ['module' => $module, 'entity_id' => $entityId]);
    }

    private function logs(string $module, string $entityId): array
    {
        return $this->fetchAll('SELECT id, actor_user_id, actor_email, module, action, entity_id, level, message, metadata, created_at FROM audit_logs WHERE module=:module AND entity_id=:entity_id ORDER BY created_at DESC, id DESC LIMIT 100', ['module' => $module, 'entity_id' => $entityId]);
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
        return ['BIRTH' => 'Sinh', 'DEATH' => 'Tử', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi', 'HOUSEHOLD_SPLIT' => 'Tách hộ', 'HOUSEHOLD_MERGE' => 'Nhập hộ', 'HEAD_CHANGE' => 'Thay đổi chủ hộ', 'INFO_CHANGE' => 'Thay đổi thông tin', 'TEMPORARY_RESIDENCE' => 'Tạm trú', 'TEMPORARY_ABSENCE' => 'Tạm vắng'][$type] ?? 'Biến động khác';
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
}
