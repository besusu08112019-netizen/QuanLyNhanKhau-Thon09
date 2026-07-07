<?php

namespace App\Models;

use App\Core\BaseModel;

final class Report extends BaseModel
{
    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'household', 'households' => $this->householdReport($filters),
            'population', 'citizen', 'citizens' => $this->populationReport($filters),
            'temporary-residence', 'temporary_residence', 'temporary' => $this->temporaryResidenceReport($filters),
            'temporary-absence', 'temporary_absence', 'absence' => $this->temporaryAbsenceReport($filters),
            'births', 'birth' => $this->birthReport($filters),
            'deaths', 'death' => $this->deathReport($filters),
            'migration', 'movement', 'movement-summary' => $this->migrationReport($filters),
            'gender' => $this->groupedCitizenReport($filters, 'gender', 'Giới tính'),
            'age' => $this->ageReport($filters),
            'residency' => $this->groupedCitizenReport($filters, 'residency_status', 'Tình trạng cư trú'),
            'health-insurance', 'health_insurance', 'has_health_insurance', 'bhyt', 'bao-hiem-y-te' => $this->healthInsuranceReport($filters),
            'health-insurance-missing', 'bhyt-missing', 'bhyt-chua-tham-gia' => $this->healthInsuranceListReport('missing', $filters),
            'health-insurance-expiring', 'bhyt-expiring', 'bhyt-sap-het-han' => $this->healthInsuranceListReport('expiring', $filters),
            'health-insurance-expired', 'bhyt-expired', 'bhyt-het-han' => $this->healthInsuranceListReport('expired', $filters),
            'health-insurance-household', 'bhyt-household' => $this->healthInsuranceHouseholdReport($filters),
            'health-insurance-area', 'bhyt-area' => $this->healthInsuranceAreaReport($filters),
            'party-members', 'party_members', 'party_member', 'party', 'dang-vien' => $this->flagCitizenReport('Báo cáo Đảng viên', 'party_member', 'Đảng viên', $filters),
            'youth-union', 'youth_union', 'youth_union_member', 'doan-vien' => $this->flagCitizenReport('Báo cáo Đoàn viên', 'youth_union_member', 'Đoàn viên', $filters),
            'meritorious-people', 'meritorious', 'meritorious_person', 'nguoi-co-cong' => $this->flagCitizenReport('Báo cáo Người có công', 'meritorious_person', 'Người có công', $filters),
            'disabled-people', 'disabled', 'disabled_person', 'disability', 'nguoi-khuyet-tat' => $this->flagCitizenReport('Báo cáo Người khuyết tật', 'disabled_person', 'Người khuyết tật', $filters),
            'labor', 'labour', 'lao-dong' => $this->laborReport($filters),
            'elderly', 'nguoi-cao-tuoi' => $this->ageRangeReport('Báo cáo Người cao tuổi', 60, null, $filters),
            'children', 'tre-em' => $this->ageRangeReport('Báo cáo Trẻ em', null, 15, $filters),
            'poor-households', 'poor_households', 'poor', 'ho-ngheo' => $this->householdCategoryReport('Báo cáo Hộ nghèo', 'poor', $filters),
            'near-poor-households', 'near_poor_households', 'near_poor', 'ho-can-ngheo' => $this->householdCategoryReport('Báo cáo Hộ cận nghèo', 'near_poor', $filters),
            'special' => $this->specialHouseholdReport($filters),
            default => $this->summaryReport($filters),
        };
    }

    public function summaryReport(array $filters = []): array
    {
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN gender='Nam' THEN 1 ELSE 0 END),0) AS male, COALESCE(SUM(CASE WHEN gender='Nữ' THEN 1 ELSE 0 END),0) AS female, COALESCE(SUM(CASE WHEN residency_status='TEMPORARY' THEN 1 ELSE 0 END),0) AS temporary, COALESCE(SUM(CASE WHEN presence_status='AWAY' THEN 1 ELSE 0 END),0) AS away, COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16 THEN 1 ELSE 0 END),0) AS children, COALESCE(SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60 THEN 1 ELSE 0 END),0) AS elderly" . $this->flagSelects('c') . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $citizenWhere", $citizenParams) ?: [];
        $households = $this->fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN meritorious_family=1 THEN 1 ELSE 0 END),0) AS meritorious, COALESCE(SUM(CASE WHEN poor_household=1 THEN 1 ELSE 0 END),0) AS poor, COALESCE(SUM(CASE WHEN near_poor_household=1 THEN 1 ELSE 0 END),0) AS near_poor, COALESCE(SUM(CASE WHEN disabled_household=1 THEN 1 ELSE 0 END),0) AS disabled, COALESCE(SUM(CASE WHEN h.note LIKE '%Hộ chính sách%' OR h.note LIKE '%chính sách%' THEN 1 ELSE 0 END),0) AS policy, COALESCE(SUM(CASE WHEN poor_household=0 AND near_poor_household=0 AND meritorious_family=0 AND disabled_household=0 THEN 1 ELSE 0 END),0) AS normal FROM households h $householdWhere", $householdParams) ?: [];
        $total = max(1, (int) ($citizens['total'] ?? 0));
        $healthInsurance = (new Dashboard())->healthInsuranceStats($filters);
        $rows = [
            ['Tổng số hộ', (int) ($households['total'] ?? 0)],
            ['Tổng số nhân khẩu', (int) ($citizens['total'] ?? 0)],
            ['Nam', (int) ($citizens['male'] ?? 0)],
            ['Nữ', (int) ($citizens['female'] ?? 0)],
            ['Tạm trú', (int) ($citizens['temporary'] ?? 0)],
            ['Tạm vắng', (int) ($citizens['away'] ?? 0)],
            ['Có BHYT', $this->healthInsuranceCoveredText($healthInsurance)],
            ['Chưa có BHYT', $healthInsurance['uninsured'] . ' nhân khẩu'],
            ['Tỷ lệ bao phủ BHYT', $this->percentValue($healthInsurance['coverage_percent'])],
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
            ['Hộ chính sách', (int) ($households['policy'] ?? 0)],
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

    public function healthInsuranceReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        $stats = (new Dashboard())->healthInsuranceStats($filters);
        $rows = [
            ['Tổng số nhân khẩu', $stats['total']],
            ['Có BHYT', $this->healthInsuranceCoveredText($stats)],
            ['Chưa có BHYT', $stats['uninsured'] . ' nhân khẩu'],
            ['Tỷ lệ bao phủ', $this->percentValue($stats['coverage_percent'])],
        ];
        return $this->table('Báo cáo Bảo hiểm y tế', ['Chỉ tiêu', 'Số lượng / Tỷ lệ'], $rows, $filters);
    }

    public function healthInsuranceListReport(string $mode, array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        if ($mode === 'missing') $where .= ' AND COALESCE(c.has_health_insurance,0)=0';
        if ($mode === 'expired') $where .= ' AND COALESCE(c.has_health_insurance,0)=1 AND c.health_insurance_end_date IS NOT NULL AND c.health_insurance_end_date < CURDATE()';
        if ($mode === 'expiring') $where .= ' AND COALESCE(c.has_health_insurance,0)=1 AND c.health_insurance_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        $rows = $this->fetchAll("SELECT h.household_code, h.area_code, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, c.health_insurance_number, c.health_insurance_group, c.health_insurance_end_date, c.health_insurance_facility FROM citizens c INNER JOIN households h ON h.id=c.household_id $where ORDER BY h.household_code, c.full_name", $params);
        $title = [
            'missing' => 'Danh sách chưa tham gia BHYT',
            'expired' => 'Danh sách BHYT đã hết hạn',
            'expiring' => 'Danh sách BHYT sắp hết hạn 30 ngày',
        ][$mode] ?? 'Danh sách BHYT';
        return $this->table($title, ['Mã hộ','Khu vực','Mã nhân khẩu','Họ tên','Giới tính','Ngày sinh','CCCD','Số BHYT','Nhóm đối tượng','Hết hạn','Nơi KCB'], array_map(fn($r) => [$r['household_code'], $r['area_code'], $r['citizen_code'], $r['full_name'], $r['gender'], $this->date($r['date_of_birth']), $r['identity_number'], $r['health_insurance_number'], $r['health_insurance_group'], $this->date($r['health_insurance_end_date']), $r['health_insurance_facility']], $rows), $filters);
    }

    public function healthInsuranceHouseholdReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.area_code, COUNT(c.id) AS total, SUM(COALESCE(c.has_health_insurance,0)=1) AS enrolled, SUM(COALESCE(c.has_health_insurance,0)=0) AS missing, SUM(COALESCE(c.has_health_insurance,0)=1 AND (c.health_insurance_end_date IS NULL OR c.health_insurance_end_date >= CURDATE())) AS effective FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY h.id, h.household_code, h.head_citizen_name, h.area_code ORDER BY h.household_code", $params);
        return $this->table('Thống kê BHYT theo hộ', ['Mã hộ','Chủ hộ','Khu vực','Tổng nhân khẩu','Có BHYT','Còn hiệu lực','Chưa tham gia','Tỷ lệ bao phủ'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['area_code'], (int) $r['total'], (int) $r['enrolled'], (int) $r['effective'], (int) $r['missing'], $this->percent((int) $r['effective'], max(1, (int) $r['total']))], $rows), $filters);
    }

    public function healthInsuranceAreaReport(array $filters = []): array
    {
        (new Citizen())->ensureHealthInsuranceSchema();
        [$where, $params] = $this->citizenWhere($filters);
        $rows = $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Chưa phân khu') AS area, COUNT(c.id) AS total, SUM(COALESCE(c.has_health_insurance,0)=1) AS enrolled, SUM(COALESCE(c.has_health_insurance,0)=0) AS missing, SUM(COALESCE(c.has_health_insurance,0)=1 AND (c.health_insurance_end_date IS NULL OR c.health_insurance_end_date >= CURDATE())) AS effective FROM citizens c INNER JOIN households h ON h.id=c.household_id $where GROUP BY area ORDER BY area", $params);
        return $this->table('Thống kê BHYT theo khu vực', ['Khu vực','Tổng nhân khẩu','Có BHYT','Còn hiệu lực','Chưa tham gia','Tỷ lệ bao phủ'], array_map(fn($r) => [$r['area'], (int) $r['total'], (int) $r['enrolled'], (int) $r['effective'], (int) $r['missing'], $this->percent((int) $r['effective'], max(1, (int) $r['total']))], $rows), $filters);
    }

    public function laborReport(array $filters = []): array
    {
        $columns = ['employed' => 'Có việc làm', 'unemployed' => 'Chưa có việc làm', 'pupil' => 'Học sinh', 'student' => 'Sinh viên', 'retired' => 'Nghỉ hưu', 'other' => 'Khác'];
        [$where, $params] = $this->citizenWhere($filters);
        $selects = ['c.occupation'];
        foreach (['employed','unemployed','pupil','student','retired'] as $column) $selects[] = ($this->columnExists('citizens', $column) ? "c.$column" : '0') . " AS $column";
        $rows = $this->fetchAll('SELECT ' . implode(',', $selects) . " FROM citizens c INNER JOIN households h ON h.id=c.household_id $where", $params);
        $groups = array_fill_keys(array_keys($columns), 0);
        foreach ($rows as $row) $groups[$this->laborGroup($row)]++;
        $total = max(1, count($rows));
        $body = [];
        foreach ($columns as $column => $label) $body[] = [$label, ((int) ($groups[$column] ?? 0)) . ' (' . $this->percent((int) ($groups[$column] ?? 0), $total) . ')'];
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
        $where = [$this->activeHouseholdCondition('h')]; $params = [];
        if (!empty($filters['dateFrom'])) { $where[] = 'DATE(h.created_at) >= :date_from'; $params['date_from'] = $filters['dateFrom']; }
        if (!empty($filters['dateTo'])) { $where[] = 'DATE(h.created_at) <= :date_to'; $params['date_to'] = $filters['dateTo']; }
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        $category = $this->categoryKey($filters['household_type'] ?? $filters['householdType'] ?? $filters['category'] ?? '');
        if ($category) $this->addCategoryWhere($where, $params, $category);
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $where = [$this->activeCitizenCondition('c'), $this->activeHouseholdCondition('h')]; $params = [];
        if (!empty($filters['householdStatus'])) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
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
        foreach (['has_health_insurance','party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'] as $column) {
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

    private function activeHouseholdCondition(string $alias): string
    {
        return $alias . ".status NOT IN ('DELETED','ENDED','MERGED','TRANSFERRED_OUT','MOVED_OUT','INACTIVE')";
    }

    private function activeCitizenCondition(string $alias): string
    {
        return $alias . ".status <> 'DELETED' AND COALESCE(" . $alias . ".life_status,'ALIVE') <> 'DECEASED' AND COALESCE(" . $alias . ".residency_status,'PERMANENT') <> 'TRANSFERRED_OUT'";
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

    private function laborGroup(array $row): string
    {
        $occupation = $this->normalize((string) ($row['occupation'] ?? ''));
        if ((int) ($row['pupil'] ?? 0) === 1 || str_contains($occupation, 'hoc sinh')) return 'pupil';
        if ((int) ($row['student'] ?? 0) === 1 || str_contains($occupation, 'sinh vien')) return 'student';
        if ((int) ($row['retired'] ?? 0) === 1 || str_contains($occupation, 'nghi huu') || str_contains($occupation, 'huu tri')) return 'retired';
        if ((int) ($row['unemployed'] ?? 0) === 1 || str_contains($occupation, 'that nghiep') || str_contains($occupation, 'chua co viec') || str_contains($occupation, 'khong co viec')) return 'unemployed';
        if ((int) ($row['employed'] ?? 0) === 1) return 'employed';
        if ($occupation === '' || str_contains($occupation, 'khac') || str_contains($occupation, 'noi tro')) return 'other';
        return 'employed';
    }

    private function ensureReportTemplatesTable(): void
    {
        $this->execute('CREATE TABLE IF NOT EXISTS report_templates (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(80) NOT NULL, filters_json JSON NULL, is_default TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT "ACTIVE", created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, INDEX idx_report_templates_user (user_id, status), INDEX idx_report_templates_default (user_id, is_default)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]);
        return (int) ($row['total'] ?? 0) > 0;
    }

    private function flagSelects(string $alias): string
    {
        $columns = ['party_member','youth_union_member','women_union_member','farmers_union_member','veterans_union_member','elderly_union_member','meritorious_person','martyr_relative','wounded_soldier','sick_soldier','disabled_person','social_assistance','employed','unemployed','freelance_labor','out_province_labor','foreign_labor','pupil','student','retired'];
        $parts = [];
        foreach ($columns as $column) $parts[] = ', ' . ($this->columnExists('citizens', $column) ? "SUM($alias.$column=1)" : '0') . " AS $column";
        return implode('', $parts);
    }

    private function countPercent(array $row, string $key, int $total): string { $count = (int) ($row[$key] ?? 0); return $count . ' (' . $this->percent($count, $total) . ')'; }
    private function healthInsuranceCoveredText(array $stats): string { return $stats['insured'] . '/' . $stats['total'] . ' nhân khẩu'; }
    private function percentValue(float|int $value): string { return number_format((float) $value, 2, '.', '') . '%'; }
    private function percent(int $count, int $total): string { return number_format($total > 0 ? ($count * 100 / $total) : 0, 2, ',', '.') . '%'; }

    private function table(string $title, array $headers, array $rows, array $filters): array { return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'totalRows' => count($rows), 'filters' => $filters, 'generatedAt' => date('c')]; }
    private function householdCategories(array $row): string { if ((int) ($row['poor_household'] ?? 0) === 1) return 'Hộ nghèo'; if ((int) ($row['near_poor_household'] ?? 0) === 1) return 'Hộ cận nghèo'; if ((int) ($row['meritorious_family'] ?? 0) === 1) return 'Hộ có công'; if ((int) ($row['disabled_household'] ?? 0) === 1) return 'Khác'; $noteKey = $this->categoryKey((string) ($row['note'] ?? '')); if ($noteKey === 'policy') return 'Hộ chính sách'; if ($noteKey === 'escaped_poverty') return 'Hộ mới thoát nghèo'; return 'Hộ bình thường'; }
    private function date(?string $value): string { if (!$value) return ''; [$y, $m, $d] = explode('-', substr($value, 0, 10)); return "$d/$m/$y"; }
    private function residency(?string $value): string { return $value === 'TEMPORARY' ? 'Tạm trú' : 'Thường trú'; }
    private function presence(?string $value): string { return $value === 'AWAY' ? 'Đi vắng' : 'Ở nhà'; }
    private function life(?string $value): string { return $value === 'DECEASED' ? 'Đã chết' : 'Còn sống'; }
    private function movement(?string $value): string { return ['BIRTH' => 'Sinh', 'DEATH' => 'Tử', 'MOVE_IN' => 'Chuyển đến', 'MOVE_OUT' => 'Chuyển đi', 'TEMPORARY_RESIDENCE' => 'Tạm trú', 'TEMPORARY_ABSENCE' => 'Tạm vắng', 'OTHER' => 'Khác'][$value] ?? (string) $value; }

    public function center(): array
    {
        return [
            'groups' => [
                ['key' => 'population', 'title' => 'Bao cao dan cu', 'icon' => 'fa-users', 'description' => 'Nhan khau, gioi tinh, do tuoi, nghe nghiep, BHYT, Dang vien, Doan vien.', 'types' => ['population','health_insurance','health-insurance-missing','health-insurance-expiring','health-insurance-expired','health-insurance-household','health-insurance-area','children','elderly','labor','party_member','youth_union','gender','age']],
                ['key' => 'household', 'title' => 'Bao cao ho gia dinh', 'icon' => 'fa-house-chimney', 'description' => 'Danh sach ho, chu ho, khu vuc, ho ngheo va ho can ngheo.', 'types' => ['household','poor-households','near-poor-households','special']],
                ['key' => 'movement', 'title' => 'Bao cao bien dong', 'icon' => 'fa-right-left', 'description' => 'Khai sinh, khai tu, chuyen di, chuyen den, tam tru, tam vang.', 'types' => ['migration','temporary_residence','temporary_absence','births','deaths']],
                ['key' => 'gis', 'title' => 'Bao cao GIS', 'icon' => 'fa-map-location-dot', 'description' => 'Ho da dinh vi, chua dinh vi, ty le hoan thanh GPS theo khu vuc va thoi gian.', 'types' => ['gis','gis-located','gis-unlocated']],
                ['key' => 'digital_profile', 'title' => 'Bao cao Ho so so', 'icon' => 'fa-folder-open', 'description' => 'Ho so hoan chinh, thieu anh, thieu giay to va chua hoan thien.', 'types' => ['digital-profile','profile-complete','profile-missing-photo','profile-missing-documents','profile-incomplete']],
                ['key' => 'operation', 'title' => 'Bao cao dieu hanh', 'icon' => 'fa-tower-broadcast', 'description' => 'Chi tieu nhanh phuc vu dieu hanh va theo doi tien do.', 'types' => ['summary']],
                ['key' => 'summary', 'title' => 'Bao cao tong hop', 'icon' => 'fa-chart-pie', 'description' => 'Tong hop toan he thong theo nhieu dieu kien loc.', 'types' => ['summary']],
            ],
            'templates' => [
                ['key' => 'household-form', 'title' => 'Phieu quan ly ho gia dinh', 'type' => 'household'],
                ['key' => 'household-list', 'title' => 'Danh sach ho', 'type' => 'household'],
                ['key' => 'citizen-list', 'title' => 'Danh sach nhan khau', 'type' => 'population'],
                ['key' => 'children-list', 'title' => 'Danh sach tre em', 'type' => 'children'],
                ['key' => 'elderly-list', 'title' => 'Danh sach nguoi cao tuoi', 'type' => 'elderly'],
                ['key' => 'labor-list', 'title' => 'Danh sach lao dong', 'type' => 'labor'],
                ['key' => 'health-insurance-summary', 'title' => 'Thống kê Bảo hiểm y tế', 'type' => 'health_insurance'],
                ['key' => 'party-list', 'title' => 'Danh sach Dang vien', 'type' => 'party_member'],
                ['key' => 'poor-list', 'title' => 'Danh sach ho ngheo', 'type' => 'poor-households'],
                ['key' => 'near-poor-list', 'title' => 'Danh sach ho can ngheo', 'type' => 'near-poor-households'],
                ['key' => 'temporary-residence-list', 'title' => 'Danh sach tam tru', 'type' => 'temporary_residence'],
                ['key' => 'temporary-absence-list', 'title' => 'Danh sach tam vang', 'type' => 'temporary_absence'],
            ],
            'filters' => ['dateFrom','dateTo','area','householdCode','headName','householdId','citizen','gender','ageFrom','ageTo','occupation','health_insurance','has_health_insurance','party_member','youth_union_member','category','residencyStatus','presenceStatus','gpsStatus','digitalProfileStatus'],
            'exports' => ['preview','print','pdf','excel','word'],
            'scheduler' => ['ready' => true, 'enabled' => false, 'message' => 'Da chuan bi cau truc lap lich, chua bat gui tu dong.'],
        ];
    }

    public function biDashboard(array $filters = []): array
    {
        $dashboard = new \App\Models\Dashboard();
        $operation = new \App\Models\OperationCenter();
        $summary = $dashboard->summary($filters);
        $progress = $operation->progress($filters)['data']['items'] ?? [];
        return [
            'metrics' => $summary['metrics'] ?? [],
            'charts' => [
                'population' => $summary['charts']['population'] ?? [],
                'age' => $summary['charts']['ages'] ?? [],
                'gender' => $summary['charts']['population'] ?? [],
                'occupation' => $summary['charts']['occupations'] ?? [],
                'partyMembers' => $summary['charts']['partyMembers'] ?? [],
                'labor' => $summary['charts']['labor'] ?? [],
                'poverty' => $summary['charts']['poverty'] ?? [],
                'gpsProgress' => $summary['charts']['gpsProgress'] ?? [],
                'profileProgress' => $summary['charts']['profileProgress'] ?? [],
                'healthInsurance' => $summary['charts']['healthInsurance'] ?? [],
                'monthlyMovements' => $summary['charts']['monthlyChanges'] ?? [],
            ],
            'progress' => $progress,
            'filters' => $filters,
            'generatedAt' => date('c'),
        ];
    }

    public function gisReport(array $filters = [], string $mode = 'all'): array
    {
        $filters['gpsStatus'] = $mode === 'located' ? 'located' : ($mode === 'unlocated' ? 'missing' : ($filters['gpsStatus'] ?? null));
        [$where, $params] = $this->householdWhere($filters);
        $lat = $this->columnExists('households', 'latitude') ? 'h.latitude' : 'NULL';
        $lng = $this->columnExists('households', 'longitude') ? 'h.longitude' : 'NULL';
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.area_code, $lat AS latitude, $lng AS longitude, h.location_updated_at FROM households h $where ORDER BY h.area_code, h.household_code", $params);
        $body = array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['area_code'], $r['latitude'], $r['longitude'], $this->date($r['location_updated_at'] ?? '')], $rows);
        $title = $mode === 'located' ? 'Bao cao ho da dinh vi GPS' : ($mode === 'unlocated' ? 'Bao cao ho chua dinh vi GPS' : 'Bao cao GIS ho gia dinh');
        return $this->table($title, ['Ma ho','Chu ho','Dia chi','Khu vuc','Vi do','Kinh do','Ngay cap nhat GPS'], $body, $filters);
    }

    public function digitalProfileReport(array $filters = [], string $mode = 'all'): array
    {
        $filters['digitalProfileStatus'] = $mode === 'complete' ? 'complete' : ($mode === 'incomplete' ? 'incomplete' : ($filters['digitalProfileStatus'] ?? null));
        [$where, $params] = $this->householdWhere($filters);
        $hasFiles = $this->tableExists('file_attachments');
        $fileModuleWhere = 'f.module=\'household\'';
        if ($hasFiles && $this->columnExists('file_attachments', 'entity_type')) $fileModuleWhere = '(' . $fileModuleWhere . ' OR f.entity_type=\'household\')';
        $fileStatusWhere = $hasFiles && $this->columnExists('file_attachments', 'status') ? ' AND f.status=\'ACTIVE\'' : '';
        $photoParts = [];
        if ($hasFiles && $this->columnExists('file_attachments', 'file_type')) $photoParts[] = "f.file_type IN ('PHOTO','image','image/jpeg','image/png')";
        if ($hasFiles && $this->columnExists('file_attachments', 'mime_type')) $photoParts[] = "f.mime_type LIKE 'image/%'";
        if ($hasFiles && $this->columnExists('file_attachments', 'profile_section')) $photoParts[] = "f.profile_section LIKE '%photo%'";
        $photoWhere = $photoParts ? ' AND (' . implode(' OR ', $photoParts) . ')' : '';
        $photoSql = $hasFiles ? "(SELECT COUNT(*) FROM file_attachments f WHERE f.entity_id=h.id AND $fileModuleWhere$fileStatusWhere$photoWhere)" : '0';
        $docSql = $hasFiles ? "(SELECT COUNT(*) FROM file_attachments f WHERE f.entity_id=h.id AND $fileModuleWhere$fileStatusWhere)" : '0';
        $rows = $this->fetchAll("SELECT h.household_code, h.head_citizen_name, h.address, h.area_code, $photoSql AS photo_count, $docSql AS document_count FROM households h $where ORDER BY h.household_code", $params);
        if ($mode === 'complete') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) > 0 && (int) ($r['document_count'] ?? 0) > 0));
        if ($mode === 'incomplete') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) === 0 || (int) ($r['document_count'] ?? 0) === 0));
        if ($mode === 'missing_photo') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['photo_count'] ?? 0) === 0));
        if ($mode === 'missing_documents') $rows = array_values(array_filter($rows, fn($r) => (int) ($r['document_count'] ?? 0) === 0));
        $title = ['complete' => 'Bao cao ho so so hoan chinh', 'missing_photo' => 'Bao cao ho so thieu anh', 'missing_documents' => 'Bao cao ho so thieu giay to', 'incomplete' => 'Bao cao ho so chua hoan thien'][$mode] ?? 'Bao cao Ho so so';
        return $this->table($title, ['Ma ho','Chu ho','Dia chi','Khu vuc','So anh','So giay to','Trang thai'], array_map(fn($r) => [$r['household_code'], $r['head_citizen_name'], $r['address'], $r['area_code'], (int) $r['photo_count'], (int) $r['document_count'], ((int) $r['photo_count'] > 0 && (int) $r['document_count'] > 0) ? 'Hoan chinh' : 'Chua hoan thien'], $rows), $filters);
    }

    public function templates(int $userId): array
    {
        $this->ensureReportTemplatesTable();
        return $this->fetchAll('SELECT id, name, type, filters_json, is_default, created_at, updated_at FROM report_templates WHERE user_id=:user_id AND status="ACTIVE" ORDER BY is_default DESC, updated_at DESC, id DESC', ['user_id' => $userId]);
    }

    public function saveTemplate(int $userId, array $input): array
    {
        $this->ensureReportTemplatesTable();
        $name = trim((string) ($input['name'] ?? '')) ?: 'Mau bao cao';
        $type = trim((string) ($input['type'] ?? 'summary')) ?: 'summary';
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];
        $isDefault = !empty($input['isDefault']) ? 1 : 0;
        if ($isDefault) $this->execute('UPDATE report_templates SET is_default=0 WHERE user_id=:user_id', ['user_id' => $userId]);
        $id = $this->insert('INSERT INTO report_templates (user_id, name, type, filters_json, is_default, status, created_at, updated_at) VALUES (:user_id,:name,:type,:filters,:is_default,"ACTIVE",NOW(),NOW())', ['user_id' => $userId, 'name' => $name, 'type' => $type, 'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'is_default' => $isDefault]);
        return $this->fetchOne('SELECT id, name, type, filters_json, is_default, created_at, updated_at FROM report_templates WHERE id=:id', ['id' => $id]) ?: ['id' => $id, 'name' => $name, 'type' => $type, 'filters_json' => json_encode($filters), 'is_default' => $isDefault];
    }

    public function deleteTemplate(int $userId, int $id): void
    {
        $this->ensureReportTemplatesTable();
        $this->execute('UPDATE report_templates SET status="DELETED", updated_at=NOW() WHERE id=:id AND user_id=:user_id', ['id' => $id, 'user_id' => $userId]);
    }

    public function setDefaultTemplate(int $userId, int $id): void
    {
        $this->ensureReportTemplatesTable();
        $this->execute('UPDATE report_templates SET is_default=0 WHERE user_id=:user_id', ['user_id' => $userId]);
        $this->execute('UPDATE report_templates SET is_default=1, updated_at=NOW() WHERE id=:id AND user_id=:user_id AND status="ACTIVE"', ['id' => $id, 'user_id' => $userId]);
    }

}
