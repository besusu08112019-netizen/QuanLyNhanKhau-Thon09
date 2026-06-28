<?php

namespace App\Models;

use App\Core\BaseModel;

final class Report extends BaseModel
{
    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'household' => $this->householdReport($filters),
            'population' => $this->populationReport($filters),
            'gender' => $this->groupedCitizenReport($filters, 'gender', 'Giới tính'),
            'age' => $this->ageReport($filters),
            'residency' => $this->groupedCitizenReport($filters, 'residency_status', 'Tình trạng cư trú'),
            'special' => $this->specialHouseholdReport($filters),
            'movement' => $this->movementReport($filters),
            default => $this->summaryReport($filters),
        };
    }

    public function summaryReport(array $filters = []): array
    {
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total, SUM(gender='Nam') AS male, SUM(gender='Nữ') AS female, SUM(residency_status='TEMPORARY') AS temporary, SUM(presence_status='AWAY') AS away, SUM(life_status='DECEASED') AS deceased FROM citizens c INNER JOIN households h ON h.id=c.household_id $citizenWhere", $citizenParams) ?: [];
        $households = $this->fetchOne("SELECT COUNT(*) AS total, SUM(meritorious_family=1) AS meritorious, SUM(poor_household=1) AS poor, SUM(near_poor_household=1) AS near_poor, SUM(disabled_household=1) AS disabled FROM households h $householdWhere", $householdParams) ?: [];
        return $this->table('Báo cáo tổng hợp', ['Chỉ tiêu', 'Số lượng'], [
            ['Tổng số hộ', (int) ($households['total'] ?? 0)],
            ['Tổng số nhân khẩu', (int) ($citizens['total'] ?? 0)],
            ['Nam', (int) ($citizens['male'] ?? 0)],
            ['Nữ', (int) ($citizens['female'] ?? 0)],
            ['Tạm trú', (int) ($citizens['temporary'] ?? 0)],
            ['Tạm vắng', (int) ($citizens['away'] ?? 0)],
            ['Đã chết', (int) ($citizens['deceased'] ?? 0)],
            ['Gia đình có công', (int) ($households['meritorious'] ?? 0)],
            ['Hộ nghèo', (int) ($households['poor'] ?? 0)],
            ['Hộ cận nghèo', (int) ($households['near_poor'] ?? 0)],
            ['Hộ có người tàn tật', (int) ($households['disabled'] ?? 0)],
        ], $filters);
    }

    public function householdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, COALESCE(v.total_members,0) AS members, COALESCE(v.at_home_count,0) AS at_home, COALESCE(v.away_count,0) AS away, h.meritorious_family, h.poor_household, h.near_poor_household, h.disabled_household FROM households h LEFT JOIN v_household_member_counts v ON v.household_id=h.id $where ORDER BY h.household_code", $params);
        return $this->table('Danh sách hộ dân', ['Mã hộ','Chủ hộ','Địa chỉ','Số điện thoại','Nhân khẩu','Ở nhà','Đi vắng','Diện hộ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], (int) $r['members'], (int) $r['at_home'], (int) $r['away'], $this->householdCategories($r)], $rows), $filters);
    }

    public function populationReport(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.relationship, c.residency_status, c.presence_status, c.life_status, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY h.household_code, CASE WHEN c.relationship='Chủ hộ' THEN 0 ELSE 1 END, c.full_name", $params);
        return $this->table('Danh sách nhân khẩu', ['Mã hộ','Mã nhân khẩu','Họ tên','Giới tính','Ngày sinh','CCCD','Quan hệ','Cư trú','Hiện tại','Trạng thái','Số điện thoại'], array_map(fn($r) => [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['relationship'], $this->residency($r['residency_status']), $this->presence($r['presence_status']), $this->life($r['life_status']), $r['phone']], $rows), $filters);
    }

    public function groupedCitizenReport(array $filters, string $field, string $label): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $fieldSql = $field === 'residency_status' ? "CASE c.residency_status WHEN 'TEMPORARY' THEN 'Tạm trú' ELSE 'Thường trú' END" : "COALESCE(NULLIF(c.$field,''),'Khác')";
        $rows = $this->fetchAll("SELECT $fieldSql AS label, COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY label", $params);
        return $this->table('Báo cáo theo ' . mb_strtolower($label), [$label, 'Số lượng'], array_map(fn($r) => [$r['label'], (int) $r['total']], $rows), $filters);
    }

    public function ageReport(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN '0-5' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 17 THEN '6-17' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 35 THEN '18-35' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 59 THEN '36-59' ELSE '60+' END AS label, COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params);
        return $this->table('Báo cáo theo độ tuổi', ['Độ tuổi', 'Số lượng'], array_map(fn($r) => [$r['label'], (int) $r['total']], $rows), $filters);
    }

    public function specialHouseholdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $where .= " AND (h.meritorious_family=1 OR h.poor_household=1 OR h.near_poor_household=1 OR h.disabled_household=1)";
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, h.meritorious_family, h.poor_household, h.near_poor_household, h.disabled_household FROM households h $where ORDER BY h.household_code", $params);
        return $this->table('Danh sách người có công, hộ nghèo, cận nghèo, tàn tật', ['Mã hộ','Chủ hộ','Địa chỉ','Số điện thoại','Diện hộ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], $this->householdCategories($r)], $rows), $filters);
    }

    public function movementReport(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        $dateFrom = trim((string) ($filters['dateFrom'] ?? ''));
        $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateFrom) { $where[] = 'DATE(m.movement_date) >= :date_from'; $params['date_from'] = $dateFrom; }
        if ($dateTo) { $where[] = 'DATE(m.movement_date) <= :date_to'; $params['date_to'] = $dateTo; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $rows = $this->fetchAll("SELECT m.movement_type, COUNT(*) AS total FROM movements m $sqlWhere GROUP BY m.movement_type ORDER BY m.movement_type", $params);
        return $this->table('Báo cáo biến động dân cư', ['Loại biến động', 'Số lượng'], array_map(fn($r) => [$this->movement($r['movement_type']), (int) $r['total']], $rows), $filters);
    }

    private function householdWhere(array $filters): array
    {
        $where = ['h.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(h.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(h.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $where = ['c.status <> "DELETED"', 'h.status <> "DELETED"'];
        $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(c.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(c.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['residencyStatus'])) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if (!empty($filters['presenceStatus'])) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['lifeStatus'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['lifeStatus']; }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')];
    }

    private function householdCategories(array $row): string
    {
        $items = [];
        if ((int) ($row['meritorious_family'] ?? 0) === 1) $items[] = 'Gia đình có công';
        if ((int) ($row['poor_household'] ?? 0) === 1) $items[] = 'Hộ nghèo';
        if ((int) ($row['near_poor_household'] ?? 0) === 1) $items[] = 'Hộ cận nghèo';
        if ((int) ($row['disabled_household'] ?? 0) === 1) $items[] = 'Tàn tật';
        return $items ? implode(', ', $items) : 'Không';
    }

    private function date(?string $value): string { if (!$value) return ''; [$y, $m, $d] = explode('-', substr($value, 0, 10)); return "$d/$m/$y"; }
    private function residency(?string $value): string { return $value === 'TEMPORARY' ? 'Tạm trú' : 'Thường trú'; }
    private function presence(?string $value): string { return $value === 'AWAY' ? 'Đi vắng' : 'Ở nhà'; }
    private function life(?string $value): string { return $value === 'DECEASED' ? 'Đã chết' : 'Còn sống'; }
    private function movement(?string $value): string { return ['BIRTH' => 'Sinh', 'DEATH' => 'Tử', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi'][$value] ?? (string) $value; }
}
