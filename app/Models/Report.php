<?php

namespace App\Models;

use App\Core\BaseModel;

final class Report extends BaseModel
{
    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'household', 'households' => $this->householdReport($filters),
            'population' => $this->populationReport($filters),
            'temporary-residence', 'temporary_residence' => $this->temporaryResidenceReport($filters),
            'temporary-absence', 'temporary_absence' => $this->temporaryAbsenceReport($filters),
            'births', 'birth' => $this->birthReport($filters),
            'deaths', 'death' => $this->deathReport($filters),
            'migration', 'movement', 'movement-summary' => $this->migrationReport($filters),
            'gender' => $this->groupedCitizenReport($filters, 'gender', 'Giới tính'),
            'age' => $this->ageReport($filters),
            'residency' => $this->groupedCitizenReport($filters, 'residency_status', 'Tình trạng cư trú'),
            'party-members', 'party_member', 'dang-vien' => $this->flagCitizenReport('Báo cáo Đảng viên', 'party_member', 'Đảng viên', $filters),
            'youth-union', 'youth_union_member', 'doan-vien' => $this->flagCitizenReport('Báo cáo Đoàn viên', 'youth_union_member', 'Đoàn viên', $filters),
            'meritorious-people', 'meritorious_person', 'nguoi-co-cong' => $this->flagCitizenReport('Báo cáo Người có công', 'meritorious_person', 'Người có công', $filters),
            'disabled-people', 'disabled_person', 'nguoi-khuyet-tat' => $this->flagCitizenReport('Báo cáo Người khuyết tật', 'disabled_person', 'Người khuyết tật', $filters),
            'labor', 'labour', 'lao-dong' => $this->laborReport($filters),
            'elderly', 'nguoi-cao-tuoi' => $this->ageRangeReport('Báo cáo Người cao tuổi', 60, null, $filters),
            'children', 'tre-em' => $this->ageRangeReport('Báo cáo Trẻ em', null, 15, $filters),
            'poor-households', 'ho-ngheo' => $this->householdCategoryReport('Báo cáo Hộ nghèo', 'poor', $filters),
            'near-poor-households', 'ho-can-ngheo' => $this->householdCategoryReport('Báo cáo Hộ cận nghèo', 'near_poor', $filters),
            'special' => $this->specialHouseholdReport($filters),
            default => $this->summaryReport($filters),
        };
    }

    public function summaryReport(array $filters = []): array
    {
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total, SUM(gender='Nam') AS male, SUM(gender='Nữ') AS female, SUM(residency_status='TEMPORARY') AS temporary, SUM(presence_status='AWAY') AS away, SUM(life_status='DECEASED') AS deceased, SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 15 THEN 1 ELSE 0 END) AS children, SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60 THEN 1 ELSE 0 END) AS elderly" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $citizenWhere", $citizenParams) ?: [];
        $households = $this->fetchOne("SELECT COUNT(*) AS total, SUM(meritorious_family=1) AS meritorious, SUM(poor_household=1) AS poor, SUM(near_poor_household=1) AS near_poor, SUM(disabled_household=1) AS disabled, SUM(poor_household=0 AND near_poor_household=0 AND meritorious_family=0 AND disabled_household=0) AS normal FROM households h $householdWhere", $householdParams) ?: [];
        $total = max(1, (int) ($citizens['total'] ?? 0));
        $rows = [
            ['Tổng số hộ', (int) ($households['total'] ?? 0)],
            ['Tổng số nhân khẩu', (int) ($citizens['total'] ?? 0)],
            ['Nam', (int) ($citizens['male'] ?? 0)],
            ['Nữ', (int) ($citizens['female'] ?? 0)],
            ['Tạm trú', (int) ($citizens['temporary'] ?? 0)],
            ['Tạm vắng', (int) ($citizens['away'] ?? 0)],
            ['Đảng viên', $this->countPercent($citizens, 'party_member', $total)],
            ['Đoàn viên', $this->countPercent($citizens, 'youth_union_member', $total)],
            ['Hội viên Hội Phụ nữ', $this->countPercent($citizens, 'women_union_member', $total)],
            ['Hội viên Hội Nông dân', $this->countPercent($citizens, 'farmers_union_member', $total)],
            ['Hội viên Hội Cựu chiến binh', $this->countPercent($citizens, 'veterans_union_member', $total)],
            ['Hội viên Hội Người cao tuổi', $this->countPercent($citizens, 'elderly_union_member', $total)],
            ['Người có công', $this->countPercent($citizens, 'meritorious_person', $total)],
            ['Thương binh', $this->countPercent($citizens, 'wounded_soldier', $total)],
            ['Bệnh binh', $this->countPercent($citizens, 'sick_soldier', $total)],
            ['Thân nhân liệt sĩ', $this->countPercent($citizens, 'martyr_relative', $total)],
            ['Người khuyết tật', $this->countPercent($citizens, 'disabled_person', $total)],
            ['Bảo trợ xã hội', $this->countPercent($citizens, 'social_assistance', $total)],
            ['Có việc làm', $this->countPercent($citizens, 'employed', $total)],
            ['Thất nghiệp', $this->countPercent($citizens, 'unemployed', $total)],
            ['Lao động tự do', $this->countPercent($citizens, 'freelance_labor', $total)],
            ['Lao động ngoài tỉnh', $this->countPercent($citizens, 'out_province_labor', $total)],
            ['Lao động nước ngoài', $this->countPercent($citizens, 'foreign_labor', $total)],
            ['Trẻ em', (int) ($citizens['children'] ?? 0) . ' (' . $this->percent((int) ($citizens['children'] ?? 0), $total) . ')'],
            ['Người cao tuổi', (int) ($citizens['elderly'] ?? 0) . ' (' . $this->percent((int) ($citizens['elderly'] ?? 0), $total) . ')'],
            ['Hộ nghèo', (int) ($households['poor'] ?? 0)],
            ['Hộ cận nghèo', (int) ($households['near_poor'] ?? 0)],
            ['Hộ chính sách', 0],
            ['Hộ có công', (int) ($households['meritorious'] ?? 0)],
            ['Hộ bình thường', (int) ($households['normal'] ?? 0)],
            ['Hộ khác', (int) ($households['disabled'] ?? 0)],
        ];
        return $this->table('Báo cáo tổng hợp', ['Chỉ tiêu', 'Số lượng / Tỷ lệ'], $rows, $filters);
    }

    public function householdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, COALESCE(v.total_members,0) AS members, COALESCE(v.at_home_count,0) AS at_home, COALESCE(v.away_count,0) AS away, h.meritorious_family, h.poor_household, h.near_poor_household, h.disabled_household, h.note FROM households h LEFT JOIN v_household_member_counts v ON v.household_id=h.id $where ORDER BY h.household_code", $params);
        return $this->table('Danh sách hộ dân', ['Mã hộ','Chủ hộ','Địa chỉ','Số điện thoại','Nhân khẩu','Ở nhà','Đi vắng','Diện hộ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], (int) $r['members'], (int) $r['at_home'], (int) $r['away'], $this->householdCategories($r)], $rows), $filters);
    }

    public function populationReport(array $filters = []): array { return $this->citizenListReport('Danh sách nhân khẩu', $filters); }
    public function temporaryResidenceReport(array $filters = []): array { $filters['residencyStatus'] = 'TEMPORARY'; return $this->citizenListReport('Danh sách tạm trú', $filters); }
    public function temporaryAbsenceReport(array $filters = []): array { $filters['presenceStatus'] = 'AWAY'; return $this->citizenListReport('Danh sách tạm vắng', $filters); }
    public function birthReport(array $filters = []): array { return $this->movementDetailReport('Báo cáo khai sinh', ['BIRTH'], $filters); }
    public function deathReport(array $filters = []): array { return $this->movementDetailReport('Báo cáo khai tử', ['DEATH'], $filters); }
    public function migrationReport(array $filters = []): array { return $this->movementDetailReport('Báo cáo biến động dân cư', ['BIRTH', 'DEATH', 'MOVE_IN', 'MOVE_OUT', 'TEMPORARY_RESIDENCE', 'TEMPORARY_ABSENCE', 'OTHER'], $filters); }

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
        $rows = $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN '0-5 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 14 THEN '6-14 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 17 THEN '15-17 tuổi' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 59 THEN '18-59 tuổi' ELSE 'Từ 60 tuổi trở lên' END AS label, COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params);
        return $this->table('Báo cáo theo độ tuổi', ['Độ tuổi', 'Số lượng'], array_map(fn($r) => [$r['label'], (int) $r['total']], $rows), $filters);
    }

    public function specialHouseholdReport(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $where .= " AND (h.meritorious_family=1 OR h.poor_household=1 OR h.near_poor_household=1 OR h.disabled_household=1)";
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.phone, h.meritorious_family, h.poor_household, h.near_poor_household, h.disabled_household, h.note FROM households h $where ORDER BY h.household_code", $params);
        return $this->table('Danh sách người có công, hộ nghèo, cận nghèo, khuyết tật', ['Mã hộ','Chủ hộ','Địa chỉ','Số điện thoại','Diện hộ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['phone'], $this->householdCategories($r)], $rows), $filters);
    }

    public function householdCategoryReport(string $title, string $category, array $filters = []): array
    {
        $filters['category'] = $category;
        return $this->householdReport($filters + ['reportTitle' => $title]);
    }

    public function flagCitizenReport(string $title, string $column, string $label, array $filters = []): array
    {
        if (!$this->columnExists('citizens', $column)) return $this->table($title, ['Chỉ tiêu', 'Số lượng'], [[$label, '0 (0%)']], $filters);
        [$where, $params] = $this->citizenWhere($filters);
        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params)['total'] ?? 0);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where AND c.$column=1 ORDER BY h.household_code, c.full_name", $params);
        $headers = ['Mã hộ','Mã nhân khẩu','Họ tên','Giới tính','Ngày sinh','CCCD','Số điện thoại'];
        $body = [["Tổng $label", count($rows) . ' / ' . $total . ' (' . $this->percent(count($rows), max(1, $total)) . ')']];
        foreach ($rows as $r) $body[] = [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['phone']];
        return $this->table($title, $headers, $body, $filters);
    }

    public function laborReport(array $filters = []): array
    {
        $columns = ['employed' => 'Có việc làm', 'unemployed' => 'Thất nghiệp', 'freelance_labor' => 'Lao động tự do', 'out_province_labor' => 'Lao động ngoài tỉnh', 'foreign_labor' => 'Lao động nước ngoài', 'pupil' => 'Học sinh', 'student' => 'Sinh viên', 'retired' => 'Nghỉ hưu'];
        [$where, $params] = $this->citizenWhere($filters);
        $selects = [];
        foreach ($columns as $column => $label) $selects[] = ($this->columnExists('citizens', $column) ? "SUM(c.$column=1)" : '0') . " AS $column";
        $row = $this->fetchOne('SELECT COUNT(*) AS total, ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params) ?: [];
        $total = max(1, (int) ($row['total'] ?? 0));
        $body = [];
        foreach ($columns as $column => $label) $body[] = [$label, ((int) ($row[$column] ?? 0)) . ' (' . $this->percent((int) ($row[$column] ?? 0), $total) . ')'];
        return $this->table('Báo cáo Lao động', ['Nhóm lao động','Số lượng / Tỷ lệ'], $body, $filters);
    }

    public function ageRangeReport(string $title, ?int $from, ?int $to, array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        if ($from !== null) { $where .= ' AND TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= :age_from_report'; $params['age_from_report'] = $from; }
        if ($to !== null) { $where .= ' AND TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= :age_to_report'; $params['age_to_report'] = $to; }
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY c.date_of_birth, c.full_name", $params);
        return $this->table($title, ['Mã hộ','Mã nhân khẩu','Họ tên','Giới tính','Ngày sinh','CCCD','Số điện thoại'], array_map(fn($r) => [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['phone']], $rows), $filters);
    }

    public function movementReport(array $filters = []): array { return $this->migrationReport($filters); }

    private function citizenListReport(string $title, array $filters): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.relationship, c.residency_status, c.presence_status, c.life_status, c.phone FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY h.household_code, CASE WHEN c.relationship='Chủ hộ' THEN 0 ELSE 1 END, c.full_name", $params);
        return $this->table($title, ['Mã hộ','Mã nhân khẩu','Họ tên','Giới tính','Ngày sinh','CCCD','Quan hệ','Cư trú','Hiện tại','Trạng thái','Số điện thoại'], array_map(fn($r) => [$r['household_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['relationship'], $this->residency($r['residency_status']), $this->presence($r['presence_status']), $this->life($r['life_status']), $r['phone']], $rows), $filters);
    }

    private function movementDetailReport(string $title, array $types, array $filters): array
    {
        $where = ['m.status <> "DELETED"']; $params = [];
        if ($types) { $placeholders = []; foreach ($types as $index => $type) { $key = 'type_' . $index; $placeholders[] = ':' . $key; $params[$key] = $type; } $where[] = 'm.type IN (' . implode(',', $placeholders) . ')'; }
        $dateFrom = trim((string) ($filters['dateFrom'] ?? '')); $dateTo = trim((string) ($filters['dateTo'] ?? ''));
        if ($dateFrom) { $where[] = 'DATE(m.effective_date) >= :date_from'; $params['date_from'] = $dateFrom; }
        if ($dateTo) { $where[] = 'DATE(m.effective_date) <= :date_to'; $params['date_to'] = $dateTo; }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $rows = $this->fetchAll("SELECT m.type, m.effective_date, m.from_address, m.to_address, m.reason, m.document_number, c.full_name, c.identity_number, c.citizen_code, h.household_code FROM movements m INNER JOIN citizens c ON c.id=m.citizen_id LEFT JOIN households h ON h.id=m.household_id $sqlWhere ORDER BY m.effective_date DESC, m.id DESC", $params);
        return $this->table($title, ['Loại','Ngày','Mã hộ','Mã nhân khẩu','Họ tên','CCCD','Từ nơi','Đến nơi','Lý do','Số giấy tờ'], array_map(fn($r) => [$this->movement($r['type']), $this->date($r['effective_date']), $r['household_code'], $r['citizen_code'], $r['full_name'], $r['identity_number'], $r['from_address'], $r['to_address'], $r['reason'], $r['document_number']], $rows), $filters);
    }

    private function householdWhere(array $filters): array
    {
        $where = ['h.status <> "DELETED"']; $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(h.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(h.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category) $this->addCategoryWhere($where, $params, $category);
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $where = ['c.status <> "DELETED"', 'h.status <> "DELETED"']; $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(c.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(c.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['residencyStatus'])) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if (!empty($filters['presenceStatus'])) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if (!empty($filters['lifeStatus'])) { $where[] = 'c.life_status = :life_status'; $params['life_status'] = $filters['lifeStatus']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category) $this->addCategoryWhere($where, $params, $category);
        if (!empty($filters['gender'])) { $where[] = 'c.gender = :gender'; $params['gender'] = $filters['gender']; }
        if (!empty($filters['ageFrom'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= :age_from'; $params['age_from'] = (int) $filters['ageFrom']; }
        if (!empty($filters['ageTo'])) { $where[] = 'TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= :age_to'; $params['age_to'] = (int) $filters['ageTo']; }
        if (!empty($filters['ethnicity'])) { $where[] = 'c.ethnicity LIKE :ethnicity'; $params['ethnicity'] = '%' . $filters['ethnicity'] . '%'; }
        if (!empty($filters['religion'])) { $where[] = 'c.religion LIKE :religion'; $params['religion'] = '%' . $filters['religion'] . '%'; }
        if (!empty($filters['occupation'])) { $where[] = 'c.occupation LIKE :occupation'; $params['occupation'] = '%' . $filters['occupation'] . '%'; }
        foreach (['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $column) {
            if (($filters[$column] ?? null) !== null && $filters[$column] !== '' && $this->columnExists('citizens', $column)) { $where[] = 'c.' . $column . ' = :' . $column; $params[$column] = (int) $filters[$column]; }
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function addCategoryWhere(array &$where, array &$params, string $category): void
    {
        match ($category) {
            'poor' => $where[] = 'h.poor_household = 1',
            'near_poor' => $where[] = 'h.near_poor_household = 1',
            'meritorious' => $where[] = 'h.meritorious_family = 1',
            'normal' => $where[] = 'h.poor_household = 0 AND h.near_poor_household = 0 AND h.meritorious_family = 0 AND h.disabled_household = 0',
            'other' => $where[] = 'h.disabled_household = 1',
            'escaped_poverty', 'policy' => $this->addTextCategoryWhere($where, $params, $category),
            default => null,
        };
    }

    private function addTextCategoryWhere(array &$where, array &$params, string $category): void
    {
        $label = ['escaped_poverty' => 'Hộ mới thoát nghèo', 'policy' => 'Hộ chính sách'][$category] ?? $category;
        $where[] = '(h.note LIKE :category_label OR h.note LIKE :category_key)';
        $params['category_label'] = '%' . $label . '%';
        $params['category_key'] = '%' . str_replace('_', ' ', $category) . '%';
    }

    private function categoryKey(mixed $value): string
    {
        $text = $this->normalize((string) $value);
        if ($text === '') return '';
        return match (true) {
            str_contains($text, 'can ngheo') || str_contains($text, 'near poor') => 'near_poor',
            str_contains($text, 'moi thoat ngheo') || str_contains($text, 'thoat ngheo') || str_contains($text, 'escaped poverty') => 'escaped_poverty',
            str_contains($text, 'chinh sach') || str_contains($text, 'policy') => 'policy',
            str_contains($text, 'co cong') || str_contains($text, 'gia dinh co cong') || str_contains($text, 'meritorious') => 'meritorious',
            str_contains($text, 'binh thuong') || str_contains($text, 'normal') || $text === 'khong' => 'normal',
            str_contains($text, 'khac') || str_contains($text, 'tan tat') || str_contains($text, 'khuyet tat') || str_contains($text, 'other') => 'other',
            str_contains($text, 'ngheo') || str_contains($text, 'poor') => 'poor',
            default => '',
        };
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function flagSelects(string $alias): string
    {
        $columns = ['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'];
        $parts = [];
        foreach ($columns as $column) $parts[] = ', ' . ($this->columnExists('citizens', $column) ? "SUM($alias.$column=1)" : '0') . " AS $column";
        return implode('', $parts);
    }

    private function countPercent(array $row, string $key, int $total): string { $count = (int) ($row[$key] ?? 0); return $count . ' (' . $this->percent($count, $total) . ')'; }
    private function percent(int $count, int $total): string { return number_format($total > 0 ? ($count * 100 / $total) : 0, 2, ',', '.') . '%'; }

    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')]; }
    private function householdCategories(array $row): string { if ((int) ($row['poor_household'] ?? 0) === 1) return 'Hộ nghèo'; if ((int) ($row['near_poor_household'] ?? 0) === 1) return 'Hộ cận nghèo'; if ((int) ($row['meritorious_family'] ?? 0) === 1) return 'Hộ có công'; if ((int) ($row['disabled_household'] ?? 0) === 1) return 'Khác'; $noteKey = $this->categoryKey((string) ($row['note'] ?? '')); if ($noteKey === 'policy') return 'Hộ chính sách'; if ($noteKey === 'escaped_poverty') return 'Hộ mới thoát nghèo'; return 'Hộ bình thường'; }
    private function date(?string $value): string { if (!$value) return ''; [$y, $m, $d] = explode('-', substr($value, 0, 10)); return "$d/$m/$y"; }
    private function residency(?string $value): string { return $value === 'TEMPORARY' ? 'Tạm trú' : 'Thường trú'; }
    private function presence(?string $value): string { return $value === 'AWAY' ? 'Đi vắng' : 'Ở nhà'; }
    private function life(?string $value): string { return $value === 'DECEASED' ? 'Đã chết' : 'Còn sống'; }
    private function movement(?string $value): string { return ['BIRTH' => 'Sinh', 'DEATH' => 'Tử', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi', 'TEMPORARY_RESIDENCE' => 'Tạm trú', 'TEMPORARY_ABSENCE' => 'Tạm vắng', 'OTHER' => 'Khác'][$value] ?? (string) $value; }
}
