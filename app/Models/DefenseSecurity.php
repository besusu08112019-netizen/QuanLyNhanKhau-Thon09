<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;

final class DefenseSecurity extends BaseModel
{
    private const SETTINGS = [
        'nvqs_warning_age' => 16,
        'nvqs_registration_age' => 17,
        'nvqs_call_age' => 18,
        'nvqs_follow_end_age' => 25,
        'nvqs_extended_follow_end_age' => 27,
        'nvqs_warning_months_before' => 12,
    ];

    private const YES_NO = ['YES' => 'CÃ³', 'NO' => 'KhÃ´ng'];
    private const PRELIMINARY = ['NOT_UPDATED' => 'ChÆ°a cáº­p nháº­t', 'PENDING' => 'Chá» káº¿t quáº£', 'PASSED' => 'Äáº¡t', 'FAILED' => 'KhÃ´ng Ä‘áº¡t'];
    private const MEDICAL = ['NOT_UPDATED' => 'ChÆ°a cáº­p nháº­t', 'PENDING' => 'Chá» káº¿t quáº£', 'PASSED' => 'Äáº¡t', 'FAILED' => 'KhÃ´ng Ä‘áº¡t'];
    private const ELIGIBILITY = ['UNKNOWN' => 'ChÆ°a xÃ¡c Ä‘á»‹nh', 'ELIGIBLE' => 'Äá»§ Ä‘iá»u kiá»‡n', 'INELIGIBLE' => 'KhÃ´ng Ä‘á»§ Ä‘iá»u kiá»‡n', 'DEFERRED' => 'Táº¡m hoÃ£n', 'EXEMPT' => 'Miá»…n'];
    private const SELECTION = ['NOT_SELECTED' => 'ChÆ°a trÃºng tuyá»ƒn', 'SELECTED' => 'TrÃºng tuyá»ƒn', 'ENLISTED' => 'ÄÃ£ nháº­p ngÅ©'];
    private const MILITIA_TYPES = ['CORE' => 'DÃ¢n quÃ¢n nÃ²ng cá»‘t', 'MOBILE' => 'DÃ¢n quÃ¢n cÆ¡ Ä‘á»™ng', 'ON_SITE' => 'DÃ¢n quÃ¢n táº¡i chá»—', 'SPECIALIZED' => 'DÃ¢n quÃ¢n chuyÃªn mÃ´n', 'OTHER' => 'KhÃ¡c'];
    private const PARTICIPATION = ['ACTIVE' => 'Äang tham gia', 'PAUSED' => 'Táº¡m nghá»‰', 'COMPLETED' => 'ÄÃ£ hoÃ n thÃ nh', 'ENDED' => 'ThÃ´i tham gia'];
    private const SECURITY_POSITIONS = ['LEADER' => 'Tá»• trÆ°á»Ÿng', 'DEPUTY' => 'Tá»• phÃ³', 'MEMBER' => 'Tá»• viÃªn'];
    private const SECURITY_STATUS = ['ACTIVE' => 'Äang hoáº¡t Ä‘á»™ng', 'PAUSED' => 'Táº¡m nghá»‰', 'ENDED' => 'ThÃ´i tham gia'];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS defense_security_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  setting_key VARCHAR(80) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  applied_year INT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_defense_setting (village_id, setting_key, applied_year),
  KEY idx_defense_setting_village (village_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS defense_nvqs_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  recruitment_year INT NOT NULL,
  registered_status ENUM('YES','NO') NOT NULL DEFAULT 'NO',
  registration_date DATE NULL,
  preliminary_status ENUM('NOT_UPDATED','PENDING','PASSED','FAILED') NOT NULL DEFAULT 'NOT_UPDATED',
  preliminary_date DATE NULL,
  medical_exam_status ENUM('NOT_UPDATED','PENDING','PASSED','FAILED') NOT NULL DEFAULT 'NOT_UPDATED',
  medical_exam_date DATE NULL,
  health_classification VARCHAR(80) NULL,
  eligibility_status ENUM('UNKNOWN','ELIGIBLE','INELIGIBLE','DEFERRED','EXEMPT') NOT NULL DEFAULT 'UNKNOWN',
  deferment_reason VARCHAR(255) NULL,
  exemption_reason VARCHAR(255) NULL,
  selection_status ENUM('NOT_SELECTED','SELECTED','ENLISTED') NOT NULL DEFAULT 'NOT_SELECTED',
  order_received TINYINT(1) NOT NULL DEFAULT 0,
  enlistment_date DATE NULL,
  enlistment_unit VARCHAR(255) NULL,
  active_service TINYINT(1) NOT NULL DEFAULT 0,
  discharge_date DATE NULL,
  discharge_unit VARCHAR(255) NULL,
  completed_service TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_defense_nvqs_year (village_id, citizen_id, recruitment_year),
  KEY idx_defense_nvqs_citizen (citizen_id),
  KEY idx_defense_nvqs_year (village_id, recruitment_year),
  CONSTRAINT fk_defense_nvqs_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS defense_militia_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  militia_type ENUM('CORE','MOBILE','ON_SITE','SPECIALIZED','OTHER') NOT NULL DEFAULT 'CORE',
  position_name VARCHAR(120) NULL,
  unit_name VARCHAR(255) NULL,
  joined_date DATE NULL,
  ended_date DATE NULL,
  training_name VARCHAR(255) NULL,
  training_date DATE NULL,
  training_result VARCHAR(255) NULL,
  participation_status ENUM('ACTIVE','PAUSED','COMPLETED','ENDED') NOT NULL DEFAULT 'ACTIVE',
  reason VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  KEY idx_defense_militia_village (village_id),
  KEY idx_defense_militia_citizen (citizen_id),
  CONSTRAINT fk_defense_militia_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS defense_security_force_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  team_name VARCHAR(255) NOT NULL,
  position_code ENUM('LEADER','DEPUTY','MEMBER') NOT NULL DEFAULT 'MEMBER',
  joined_date DATE NULL,
  ended_date DATE NULL,
  area_in_charge VARCHAR(255) NULL,
  participation_status ENUM('ACTIVE','PAUSED','ENDED') NOT NULL DEFAULT 'ACTIVE',
  reason VARCHAR(255) NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  status ENUM('ACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
  KEY idx_defense_security_force_village (village_id),
  KEY idx_defense_security_force_citizen (citizen_id),
  KEY idx_defense_security_force_team (village_id, team_name),
  CONSTRAINT fk_defense_security_force_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->ensureDefaultSettings();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'settings' => $this->settings(),
            'yes_no' => $this->pairs(self::YES_NO),
            'nvqs_preliminary_statuses' => $this->pairs(self::PRELIMINARY),
            'nvqs_medical_statuses' => $this->pairs(self::MEDICAL),
            'nvqs_eligibility_statuses' => $this->pairs(self::ELIGIBILITY),
            'nvqs_selection_statuses' => $this->pairs(self::SELECTION),
            'militia_types' => $this->pairs(self::MILITIA_TYPES),
            'participation_statuses' => $this->pairs(self::PARTICIPATION),
            'security_positions' => $this->pairs(self::SECURITY_POSITIONS),
            'security_statuses' => $this->pairs(self::SECURITY_STATUS),
        ];
    }

    public function dashboard(array $filters = []): array
    {
        $this->ensureSchema();
        $year = $this->year($filters);
        $settings = $this->settings($year);
        return [
            'year' => $year,
            'settings' => $settings,
            'nvqs' => [
                'warning_age' => $this->metricTotal('nvqs', $year, 'warning_age'),
                'registration_age' => $this->metricTotal('nvqs', $year, 'registration_age'),
                'tracking_age' => $this->metricTotal('nvqs', $year, 'tracking_age'),
                'registered' => $this->metricTotal('nvqs', $year, 'registered'),
                'unregistered' => $this->metricTotal('nvqs', $year, 'unregistered'),
                'preliminary_done' => $this->metricTotal('nvqs', $year, 'preliminary_done'),
                'preliminary_missing' => $this->metricTotal('nvqs', $year, 'preliminary_missing'),
                'medical_done' => $this->metricTotal('nvqs', $year, 'medical_done'),
                'medical_missing' => $this->metricTotal('nvqs', $year, 'medical_missing'),
                'eligible' => $this->metricTotal('nvqs', $year, 'eligible'),
                'deferred' => $this->metricTotal('nvqs', $year, 'deferred'),
                'exempt' => $this->metricTotal('nvqs', $year, 'exempt'),
                'selected' => $this->metricTotal('nvqs', $year, 'selected'),
                'enlisted' => $this->metricTotal('nvqs', $year, 'enlisted'),
                'active_service' => $this->metricTotal('nvqs', $year, 'active_service'),
                'discharged' => $this->metricTotal('nvqs', $year, 'discharged'),
            ],
            'militia' => [
                'total' => $this->metricTotal('militia', $year, 'total'),
                'active' => $this->metricTotal('militia', $year, 'active'),
                'completed_or_ended' => $this->metricTotal('militia', $year, 'completed_or_ended'),
            ],
            'security_force' => [
                'total' => $this->metricTotal('security_force', $year, 'total'),
                'leaders' => $this->metricTotal('security_force', $year, 'leaders'),
                'deputies' => $this->metricTotal('security_force', $year, 'deputies'),
                'members' => $this->metricTotal('security_force', $year, 'members'),
                'active' => $this->metricTotal('security_force', $year, 'active'),
            ],
            'generatedAt' => date('c'),
        ];
    }

    public function searchCitizens(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) return [];
        $limit = max(1, min(20, $limit));
        $keyword = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $rows = $this->fetchAll('SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.address, h.area_code FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE ' . $this->activeCitizenCondition('c') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ') AND (LOWER(c.full_name) LIKE :q OR LOWER(c.citizen_code) LIKE :q OR LOWER(COALESCE(h.household_code,"")) LIKE :q OR LOWER(COALESCE(h.address,"")) LIKE :q OR YEAR(c.date_of_birth)=:year_query) ORDER BY c.full_name ASC LIMIT ' . $limit, $this->withTenant(['q' => $keyword, 'year_query' => ctype_digit($query) ? (int) $query : 0]));
        if (count($rows) < $limit) $rows = $this->mergeCitizenSearchRows($rows, $query, $limit);
        return array_map(fn($row) => $this->normalizeCitizen($row), $rows);
    }

    public function paginateNvqs(array $filters): array
    {
        $this->ensureSchema();
        if (in_array((string) ($filters['metric'] ?? ''), ['warning_age','registration_age','tracking_age','unregistered','preliminary_missing','medical_missing'], true)) return $this->paginateNvqsCandidates($filters);
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->nvqsWhere($filters);
        $from = $this->personJoin('defense_nvqs_records', 'n');
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $params['kpi_reason'] = $this->kpiReason((string) ($filters['metric'] ?? ''), $this->year($filters));
        $rows = $this->fetchAll("SELECT n.*, :kpi_reason AS kpi_reason, " . $this->citizenSelect() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalizeNvqs($row), $rows), $page, $pageSize, $total);
    }

    public function paginateMilitia(array $filters): array { return $this->paginateForce('defense_militia_records', 'm', $filters, 'normalizeMilitia'); }
    public function paginateSecurityForce(array $filters): array { return $this->paginateForce('defense_security_force_records', 's', $filters, 'normalizeSecurityForce'); }
    public function findNvqs(int $id): ?array { return $this->findRecord('defense_nvqs_records', 'n', $id, 'normalizeNvqs'); }
    public function findMilitia(int $id): ?array { return $this->findRecord('defense_militia_records', 'm', $id, 'normalizeMilitia'); }
    public function findSecurityForce(int $id): ?array { return $this->findRecord('defense_security_force_records', 's', $id, 'normalizeSecurityForce'); }

    public function saveNvqs(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = $this->nvqsParams($data, $userId);
        if ($id && !$this->findNvqs($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ NVQS');
        if (!$id && $this->existingNvqs((int) $params['citizen_id'], (int) $params['recruitment_year'])) throw new RuntimeException('NhÃ¢n kháº©u nÃ y Ä‘Ã£ cÃ³ há»“ sÆ¡ NVQS trong nÄƒm tuyá»ƒn quÃ¢n Ä‘Ã£ chá»n.');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE defense_nvqs_records SET citizen_id=:citizen_id,recruitment_year=:recruitment_year,registered_status=:registered_status,registration_date=:registration_date,preliminary_status=:preliminary_status,preliminary_date=:preliminary_date,medical_exam_status=:medical_exam_status,medical_exam_date=:medical_exam_date,health_classification=:health_classification,eligibility_status=:eligibility_status,deferment_reason=:deferment_reason,exemption_reason=:exemption_reason,selection_status=:selection_status,order_received=:order_received,enlistment_date=:enlistment_date,enlistment_unit=:enlistment_unit,active_service=:active_service,discharge_date=:discharge_date,discharge_unit=:discharge_unit,completed_service=:completed_service,note=:note,updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('defense_nvqs_records'), $this->withTenant($params));
            return $this->findNvqs($id);
        }
        $columns = array_keys($params);
        $this->addTenantInsert('defense_nvqs_records', $columns, $params);
        $newId = $this->insert('INSERT INTO defense_nvqs_records (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->findNvqs($newId);
    }

    public function saveMilitia(array $data, int $userId, ?int $id = null): array
    {
        return $this->saveGeneric('defense_militia_records', $this->militiaParams($data, $userId), $id, 'findMilitia');
    }

    public function saveSecurityForce(array $data, int $userId, ?int $id = null): array
    {
        return $this->saveGeneric('defense_security_force_records', $this->securityForceParams($data, $userId), $id, 'findSecurityForce');
    }

    public function deleteNvqs(int $id, int $userId): void { $this->softDelete('defense_nvqs_records', 'findNvqs', $id, $userId); }
    public function deleteMilitia(int $id, int $userId): void { $this->softDelete('defense_militia_records', 'findMilitia', $id, $userId); }
    public function deleteSecurityForce(int $id, int $userId): void { $this->softDelete('defense_security_force_records', 'findSecurityForce', $id, $userId); }

    public function citizenSummary(int $citizenId): array
    {
        $this->ensureSchema();
        $citizen = $this->citizenExists($citizenId);
        if (!$citizen) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u');
        $year = (int) date('Y');
        return [
            'citizen' => $this->normalizeCitizen($citizen),
            'nvqs' => array_map(fn($r) => $this->normalizeNvqs($r), $this->fetchAll('SELECT n.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_nvqs_records', 'n') . ' WHERE n.citizen_id=:citizen_id AND n.status<>"DELETED" AND ' . $this->tenantWhere('n', 'defense_nvqs_records') . ' ORDER BY n.recruitment_year DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'militia' => array_map(fn($r) => $this->normalizeMilitia($r), $this->fetchAll('SELECT m.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_militia_records', 'm') . ' WHERE m.citizen_id=:citizen_id AND m.status<>"DELETED" AND ' . $this->tenantWhere('m', 'defense_militia_records') . ' ORDER BY COALESCE(m.joined_date,m.created_at) DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'security_force' => array_map(fn($r) => $this->normalizeSecurityForce($r), $this->fetchAll('SELECT s.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_security_force_records', 's') . ' WHERE s.citizen_id=:citizen_id AND s.status<>"DELETED" AND ' . $this->tenantWhere('s', 'defense_security_force_records') . ' ORDER BY COALESCE(s.joined_date,s.created_at) DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'warnings' => $this->citizenNvqsWarnings($citizen, $year),
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        $this->ensureSchema();
        $mode = str_replace('-', '_', $mode);
        if ($mode === 'summary') {
            $data = $this->dashboard($filters);
            return $this->table('BÃ¡o cÃ¡o Quá»‘c phÃ²ng - An ninh', ['Chá»‰ tiÃªu', 'Sá»‘ lÆ°á»£ng'], [
                ['Sáº¯p Ä‘áº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS', $data['nvqs']['warning_age']],
                ['Äáº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS', $data['nvqs']['registration_age']],
                ['Trong Ä‘á»™ tuá»•i cáº§n theo dÃµi tuyá»ƒn quÃ¢n', $data['nvqs']['tracking_age']],
                ['ÄÃ£ Ä‘Äƒng kÃ½ NVQS', $data['nvqs']['registered']],
                ['ChÆ°a Ä‘Äƒng kÃ½ NVQS', $data['nvqs']['unregistered']],
                ['ÄÃ£ sÆ¡ tuyá»ƒn', $data['nvqs']['preliminary_done']],
                ['ÄÃ£ khÃ¡m tuyá»ƒn', $data['nvqs']['medical_done']],
                ['Tá»•ng dÃ¢n quÃ¢n', $data['militia']['total']],
                ['Tá»•ng lá»±c lÆ°á»£ng ANTT cÆ¡ sá»Ÿ', $data['security_force']['total']],
            ], $filters);
        }
        if (str_starts_with($mode, 'militia')) return $this->forceReport('DÃ¢n quÃ¢n tá»± vá»‡', $this->paginateMilitia($filters)['items'], 'militia', $filters);
        if (str_starts_with($mode, 'security_force') || str_starts_with($mode, 'antt')) return $this->forceReport('Lá»±c lÆ°á»£ng tham gia báº£o vá»‡ ANTT á»Ÿ cÆ¡ sá»Ÿ', $this->paginateSecurityForce($filters)['items'], 'security', $filters);
        $filters['metric'] = match ($mode) {
            'upcoming_registration' => 'warning_age',
            'registration_age' => 'registration_age',
            'tracking_age' => 'tracking_age',
            'unregistered' => 'unregistered',
            'registered' => 'registered',
            'preliminary' => 'preliminary_done',
            'medical' => 'medical_done',
            'eligible' => 'eligible',
            'deferred' => 'deferred',
            'exempt' => 'exempt',
            'selected' => 'selected',
            'enlisted' => 'enlisted',
            'active_service' => 'active_service',
            'discharged' => 'discharged',
            default => $filters['metric'] ?? '',
        };
        $rows = $this->paginateNvqs($filters)['items'];
        return $this->table('Danh sÃ¡ch nghÄ©a vá»¥ quÃ¢n sá»±', ['MÃ£ NK','Há» tÃªn','NgÃ y sinh','Giá»›i tÃ­nh','MÃ£ há»™','NÄƒm','ÄÄƒng kÃ½','SÆ¡ tuyá»ƒn','KhÃ¡m tuyá»ƒn','Äiá»u kiá»‡n','Tuyá»ƒn chá»n','ÄÆ¡n vá»‹ nháº­p ngÅ©','Ghi chÃº'], array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['date_of_birth'],$r['gender'],$r['household_code'],$r['recruitment_year'],$r['registered_status_label'],$r['preliminary_status_label'],$r['medical_exam_status_label'],$r['eligibility_status_label'],$r['selection_status_label'],$r['enlistment_unit'],$r['note']], $rows), $filters);
    }

    private function paginateNvqsCandidates(array $filters): array
    {
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        $year = $this->year($filters);
        $settings = $this->settings($year);
        $ageExpr = $this->ageInYearSql('c', $year);
        $where = [$this->maleCondition('c'), $this->activeCitizenCondition('c'), $this->tenantWhere('c', 'citizens'), '(h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ')'];
        $params = $this->withTenant(['year' => $year]);
        $metric = (string) ($filters['metric'] ?? '');
        if ($metric === 'warning_age') $where[] = "$ageExpr = " . (int) $settings['nvqs_warning_age'];
        elseif ($metric === 'registration_age') $where[] = "$ageExpr = " . (int) $settings['nvqs_registration_age'];
        else $where[] = "$ageExpr BETWEEN " . (int) $settings['nvqs_call_age'] . ' AND ' . (int) $settings['nvqs_follow_end_age'];
        if ($metric === 'unregistered') $where[] = 'COALESCE(n.registered_status,"NO") <> "YES"';
        if ($metric === 'preliminary_missing') $where[] = 'COALESCE(n.preliminary_status,"NOT_UPDATED") = "NOT_UPDATED"';
        if ($metric === 'medical_missing') $where[] = 'COALESCE(n.medical_exam_status,"NOT_UPDATED") = "NOT_UPDATED"';
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(c.full_name LIKE :search OR c.citizen_code LIKE :search OR h.household_code LIKE :search OR h.address LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        $from = 'FROM citizens c LEFT JOIN households h ON h.id=c.household_id LEFT JOIN defense_nvqs_records n ON n.citizen_id=c.id AND n.recruitment_year=:year AND n.status<>"DELETED" AND ' . $this->tenantWhere('n', 'defense_nvqs_records');
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total $from $whereSql", $params) ?: [])['total'] ?? 0);
        $params['kpi_reason'] = $this->kpiReason($metric, $year);
        $rows = $this->fetchAll("SELECT n.id, c.id AS citizen_id, COALESCE(n.recruitment_year,:year) AS recruitment_year, COALESCE(n.registered_status,'NO') AS registered_status, n.registration_date, COALESCE(n.preliminary_status,'NOT_UPDATED') AS preliminary_status, n.preliminary_date, COALESCE(n.medical_exam_status,'NOT_UPDATED') AS medical_exam_status, n.medical_exam_date, n.health_classification, COALESCE(n.eligibility_status,'UNKNOWN') AS eligibility_status, n.deferment_reason, n.exemption_reason, COALESCE(n.selection_status,'NOT_SELECTED') AS selection_status, COALESCE(n.order_received,0) AS order_received, n.enlistment_date, n.enlistment_unit, COALESCE(n.active_service,0) AS active_service, n.discharge_date, n.discharge_unit, COALESCE(n.completed_service,0) AS completed_service, n.note, :kpi_reason AS kpi_reason, " . $this->citizenSelect() . " $from $whereSql ORDER BY c.full_name ASC LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalizeNvqs($row), $rows), $page, $pageSize, $total);
    }
    private function paginateForce(string $table, string $alias, array $filters, string $normalizer): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->forceWhere($table, $alias, $filters);
        $from = $this->personJoin($table, $alias);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $params['kpi_reason'] = $this->kpiReason((string) ($filters['metric'] ?? ''), $this->year($filters));
        $rows = $this->fetchAll("SELECT $alias.*, :kpi_reason AS kpi_reason, " . $this->citizenSelect() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->$normalizer($row), $rows), $page, $pageSize, $total);
    }

    private function nvqsWhere(array $filters): array
    {
        $year = $this->year($filters);
        $settings = $this->settings($year);
        $where = ['n.status <> "DELETED"', $this->tenantWhere('n', 'defense_nvqs_records'), $this->tenantWhere('c', 'citizens')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(c.full_name LIKE :search OR c.citizen_code LIKE :search OR h.household_code LIKE :search OR h.address LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        if ($year > 0) { $where[] = 'n.recruitment_year=:year'; $params['year'] = $year; }
        foreach (['registered_status','preliminary_status','medical_exam_status','eligibility_status','selection_status'] as $field) {
            $value = strtoupper(trim((string) ($filters[$field] ?? '')));
            if ($value !== '') { $where[] = "n.$field=:$field"; $params[$field] = $value; }
        }
        $this->appendNvqsMetricFilter($where, (string) ($filters['metric'] ?? ''), $year, $settings);
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['full_name'=>'c.full_name','citizen_code'=>'c.citizen_code','recruitment_year'=>'n.recruitment_year','updated_at'=>'COALESCE(n.updated_at,n.created_at)'], 'recruitment_year', 'DESC', ['c.full_name ASC'])];
    }

    private function forceWhere(string $table, string $alias, array $filters): array
    {
        $where = ["$alias.status <> 'DELETED'", $this->tenantWhere($alias, $table), $this->tenantWhere('c', 'citizens')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(c.full_name LIKE :search OR c.citizen_code LIKE :search OR h.household_code LIKE :search OR h.address LIKE :search OR ' . ($alias === 's' ? 's.team_name' : 'm.unit_name') . ' LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        $status = strtoupper(trim((string) ($filters['participation_status'] ?? $filters['status'] ?? '')));
        if ($status !== '') { $where[] = "$alias.participation_status=:participation_status"; $params['participation_status'] = $status; }
        $year = $this->year($filters);
        if ($year > 0) { $where[] = "(YEAR($alias.joined_date)=:year OR YEAR($alias.ended_date)=:year OR ($alias.joined_date IS NULL AND YEAR($alias.created_at)=:year))"; $params['year'] = $year; }
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['full_name'=>'c.full_name','citizen_code'=>'c.citizen_code','joined_date'=>"$alias.joined_date",'updated_at'=>"COALESCE($alias.updated_at,$alias.created_at)"], 'joined_date', 'DESC', ['c.full_name ASC'])];
    }

    private function appendNvqsMetricFilter(array &$where, string $metric, int $year, array $settings): void
    {
        if ($metric === '') return;
        $ageExpr = $this->ageInYearSql('c', $year);
        $metricWhere = [
            'registered' => "n.registered_status='YES'",
            'unregistered' => "n.registered_status <> 'YES'",
            'preliminary_done' => "n.preliminary_status <> 'NOT_UPDATED'",
            'medical_done' => "n.medical_exam_status <> 'NOT_UPDATED'",
            'preliminary_missing' => "n.preliminary_status = 'NOT_UPDATED'",
            'medical_missing' => "n.medical_exam_status = 'NOT_UPDATED'",
            'eligible' => "n.eligibility_status='ELIGIBLE'",
            'deferred' => "n.eligibility_status='DEFERRED'",
            'exempt' => "n.eligibility_status='EXEMPT'",
            'selected' => "n.selection_status='SELECTED'",
            'enlisted' => "(n.selection_status='ENLISTED' OR n.enlistment_date IS NOT NULL)",
            'active_service' => "n.active_service=1",
            'discharged' => "n.discharge_date IS NOT NULL",
        ];
        if (isset($metricWhere[$metric])) { $where[] = $metricWhere[$metric]; return; }
        if ($metric === 'warning_age') $where[] = "$ageExpr = " . (int) $settings['nvqs_warning_age'];
        if ($metric === 'registration_age') $where[] = "$ageExpr = " . (int) $settings['nvqs_registration_age'];
        if ($metric === 'tracking_age') $where[] = "$ageExpr BETWEEN " . (int) $settings['nvqs_call_age'] . ' AND ' . (int) $settings['nvqs_follow_end_age'];
    }

    private function personJoin(string $table, string $alias): string
    {
        return "FROM $table $alias INNER JOIN citizens c ON c.id=$alias.citizen_id LEFT JOIN households h ON h.id=c.household_id";
    }

    private function citizenSelect(): string
    {
        return 'c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.head_citizen_name, h.address, h.area_code';
    }

    private function findRecord(string $table, string $alias, int $id, string $normalizer): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT ' . $alias . '.*, ' . $this->citizenSelect() . ' ' . $this->personJoin($table, $alias) . ' WHERE ' . $alias . '.id=:id AND ' . $alias . '.status<>"DELETED" AND ' . $this->tenantWhere($alias, $table) . ' AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['id' => $id]));
        return $row ? $this->$normalizer($row) : null;
    }

    private function saveGeneric(string $table, array $params, ?int $id, string $finder): array
    {
        $this->ensureSchema();
        if ($id && !$this->$finder($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y báº£n ghi Quá»‘c phÃ²ng - An ninh');
        if ($id) {
            $params['id'] = $id;
            $sets = [];
            foreach (array_keys($params) as $key) if ($key !== 'id' && $key !== 'created_by') $sets[] = $key . '=:' . $key;
            $this->execute('UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE id=:id AND ' . $this->tenantWhere($table), $this->withTenant($params));
            return $this->$finder($id);
        }
        $columns = array_keys($params);
        $this->addTenantInsert($table, $columns, $params);
        $newId = $this->insert('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->$finder($newId);
    }

    private function softDelete(string $table, string $finder, int $id, int $userId): void
    {
        $this->ensureSchema();
        if (!$this->$finder($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y báº£n ghi Quá»‘c phÃ²ng - An ninh');
        $this->execute('UPDATE ' . $table . ' SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere($table), $this->withTenant(['id' => $id, 'user' => $userId]));
    }

    private function nvqsParams(array $data, int $userId): array
    {
        $citizenId = $this->requireCitizen($data);
        $year = (int) ($data['recruitment_year'] ?? $data['year'] ?? date('Y'));
        if ($year < 1900 || $year > 2200) throw new RuntimeException('NÄƒm tuyá»ƒn quÃ¢n khÃ´ng há»£p lá»‡');
        return [
            'citizen_id' => $citizenId,
            'recruitment_year' => $year,
            'registered_status' => $this->enum($data['registered_status'] ?? 'NO', self::YES_NO, 'NO'),
            'registration_date' => $this->dateValue($data['registration_date'] ?? ''),
            'preliminary_status' => $this->enum($data['preliminary_status'] ?? 'NOT_UPDATED', self::PRELIMINARY, 'NOT_UPDATED'),
            'preliminary_date' => $this->dateValue($data['preliminary_date'] ?? ''),
            'medical_exam_status' => $this->enum($data['medical_exam_status'] ?? 'NOT_UPDATED', self::MEDICAL, 'NOT_UPDATED'),
            'medical_exam_date' => $this->dateValue($data['medical_exam_date'] ?? ''),
            'health_classification' => $this->nullable($data['health_classification'] ?? ''),
            'eligibility_status' => $this->enum($data['eligibility_status'] ?? 'UNKNOWN', self::ELIGIBILITY, 'UNKNOWN'),
            'deferment_reason' => $this->nullable($data['deferment_reason'] ?? ''),
            'exemption_reason' => $this->nullable($data['exemption_reason'] ?? ''),
            'selection_status' => $this->enum($data['selection_status'] ?? 'NOT_SELECTED', self::SELECTION, 'NOT_SELECTED'),
            'order_received' => $this->bool($data['order_received'] ?? 0),
            'enlistment_date' => $this->dateValue($data['enlistment_date'] ?? ''),
            'enlistment_unit' => $this->nullable($data['enlistment_unit'] ?? ''),
            'active_service' => $this->bool($data['active_service'] ?? 0),
            'discharge_date' => $this->dateValue($data['discharge_date'] ?? ''),
            'discharge_unit' => $this->nullable($data['discharge_unit'] ?? ''),
            'completed_service' => $this->bool($data['completed_service'] ?? 0),
            'note' => $this->nullable($data['note'] ?? ''),
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function militiaParams(array $data, int $userId): array
    {
        return ['citizen_id'=>$this->requireCitizen($data),'militia_type'=>$this->enum($data['militia_type'] ?? 'CORE', self::MILITIA_TYPES, 'CORE'),'position_name'=>$this->nullable($data['position_name'] ?? ''),'unit_name'=>$this->nullable($data['unit_name'] ?? ''),'joined_date'=>$this->dateValue($data['joined_date'] ?? ''),'ended_date'=>$this->dateValue($data['ended_date'] ?? ''),'training_name'=>$this->nullable($data['training_name'] ?? ''),'training_date'=>$this->dateValue($data['training_date'] ?? ''),'training_result'=>$this->nullable($data['training_result'] ?? ''),'participation_status'=>$this->enum($data['participation_status'] ?? 'ACTIVE', self::PARTICIPATION, 'ACTIVE'),'reason'=>$this->nullable($data['reason'] ?? ''),'note'=>$this->nullable($data['note'] ?? ''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function securityForceParams(array $data, int $userId): array
    {
        $team = trim((string) ($data['team_name'] ?? ''));
        if ($team === '') throw new RuntimeException('Tá»• ANTT lÃ  báº¯t buá»™c');
        return ['citizen_id'=>$this->requireCitizen($data),'team_name'=>$team,'position_code'=>$this->enum($data['position_code'] ?? 'MEMBER', self::SECURITY_POSITIONS, 'MEMBER'),'joined_date'=>$this->dateValue($data['joined_date'] ?? ''),'ended_date'=>$this->dateValue($data['ended_date'] ?? ''),'area_in_charge'=>$this->nullable($data['area_in_charge'] ?? ''),'participation_status'=>$this->enum($data['participation_status'] ?? 'ACTIVE', self::SECURITY_STATUS, 'ACTIVE'),'reason'=>$this->nullable($data['reason'] ?? ''),'note'=>$this->nullable($data['note'] ?? ''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function requireCitizen(array $data): int
    {
        $id = (int) ($data['citizen_id'] ?? $data['person_id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Vui lÃ²ng chá»n nhÃ¢n kháº©u tá»« danh sÃ¡ch.');
        if (!$this->citizenExists($id)) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u trong tenant hiá»‡n táº¡i');
        return $id;
    }

    private function citizenExists(int $id): ?array
    {
        return $this->fetchOne('SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.address, h.area_code FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE c.id=:id AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ')', $this->withTenant(['id' => $id]));
    }

    private function existingNvqs(int $citizenId, int $year): ?array
    {
        return $this->fetchOne('SELECT id FROM defense_nvqs_records WHERE citizen_id=:citizen_id AND recruitment_year=:year AND status<>"DELETED" AND ' . $this->tenantWhere('defense_nvqs_records'), $this->withTenant(['citizen_id' => $citizenId, 'year' => $year]));
    }

    private function metricTotal(string $tab, int $year, string $metric): int
    {
        $filters = ['year' => $year, 'metric' => $metric, 'page' => 1, 'pageSize' => 1];
        $result = match ($tab) {
            'militia' => $this->paginateMilitia($filters),
            'security_force' => $this->paginateSecurityForce($filters),
            default => $this->paginateNvqs($filters),
        };
        return (int) ($result['total'] ?? 0);
    }

    private function kpiReason(string $metric, int $year): string
    {
        return match ($metric) {
            'warning_age' => 'Nam cÃ´ng dÃ¢n Ä‘á»§ 16 tuá»•i trong nÄƒm ' . $year . ', dá»± kiáº¿n Ä‘áº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS trong nÄƒm ' . ($year + 1) . '.',
            'registration_age' => 'Nam cÃ´ng dÃ¢n Ä‘á»§ 17 tuá»•i trong nÄƒm ' . $year . ', thuá»™c diá»‡n láº­p danh sÃ¡ch Ä‘Äƒng kÃ½ NVQS.',
            'tracking_age' => 'Nam cÃ´ng dÃ¢n tá»« Ä‘á»§ 18 tuá»•i trong Ä‘á»™ tuá»•i cáº§n theo dÃµi tuyá»ƒn quÃ¢n.',
            'unregistered' => 'Thuá»™c diá»‡n Ä‘Äƒng kÃ½/theo dÃµi NVQS nhÆ°ng chÆ°a cÃ³ thÃ´ng tin Ä‘Ã£ Ä‘Äƒng kÃ½.',
            'preliminary_missing' => 'Thuá»™c diá»‡n theo dÃµi tuyá»ƒn quÃ¢n nhÆ°ng chÆ°a cáº­p nháº­t sÆ¡ tuyá»ƒn.',
            'medical_missing' => 'Thuá»™c diá»‡n theo dÃµi tuyá»ƒn quÃ¢n nhÆ°ng chÆ°a cáº­p nháº­t khÃ¡m tuyá»ƒn.',
            'registered' => 'ÄÃ£ cÃ³ thÃ´ng tin Ä‘Äƒng kÃ½ NVQS trong nÄƒm tuyá»ƒn quÃ¢n.',
            'preliminary_done' => 'ÄÃ£ cÃ³ thÃ´ng tin sÆ¡ tuyá»ƒn NVQS.',
            'medical_done' => 'ÄÃ£ cÃ³ thÃ´ng tin khÃ¡m tuyá»ƒn NVQS.',
            'eligible' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n Ä‘á»§ Ä‘iá»u kiá»‡n.',
            'deferred' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n táº¡m hoÃ£n.',
            'exempt' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n miá»…n.',
            'selected' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n trÃºng tuyá»ƒn.',
            'enlisted' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n Ä‘Ã£ nháº­p ngÅ©.',
            'active_service' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang ghi nháº­n Ä‘ang táº¡i ngÅ©.',
            'discharged' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ cÃ³ ngÃ y xuáº¥t ngÅ©.',
            'active' => 'Há»“ sÆ¡ nghiá»‡p vá»¥ Ä‘ang á»Ÿ tráº¡ng thÃ¡i hoáº¡t Ä‘á»™ng/tham gia.',
            'completed_or_ended' => 'Há»“ sÆ¡ dÃ¢n quÃ¢n Ä‘Ã£ hoÃ n thÃ nh hoáº·c thÃ´i tham gia.',
            'leaders' => 'Há»“ sÆ¡ ANTT cÃ³ chá»©c vá»¥ Tá»• trÆ°á»Ÿng.',
            'deputies' => 'Há»“ sÆ¡ ANTT cÃ³ chá»©c vá»¥ Tá»• phÃ³.',
            'members' => 'Há»“ sÆ¡ ANTT cÃ³ chá»©c vá»¥ Tá»• viÃªn.',
            'total' => 'CÃ³ há»“ sÆ¡ nghiá»‡p vá»¥ liÃªn káº¿t vá»›i nhÃ¢n kháº©u.',
            default => '',
        };
    }

    private function countAgeGroup(int $year, int $fromAge, int $toAge): int
    {
        $ageExpr = $this->ageInYearSql('c', $year);
        $sql = 'SELECT COUNT(*) AS total FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE ' . $this->maleCondition('c') . ' AND ' . $this->activeCitizenCondition('c') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ') AND ' . $ageExpr . ' BETWEEN :from_age AND :to_age';
        return (int) (($this->fetchOne($sql, $this->withTenant(['from_age' => $fromAge, 'to_age' => $toAge])) ?: [])['total'] ?? 0);
    }

    private function countNvqsStatus(int $year, string $condition): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM defense_nvqs_records n INNER JOIN citizens c ON c.id=n.citizen_id WHERE n.status<>"DELETED" AND n.recruitment_year=:year AND ' . $this->tenantWhere('n', 'defense_nvqs_records') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $condition;
        return (int) (($this->fetchOne($sql, $this->withTenant(['year' => $year])) ?: [])['total'] ?? 0);
    }

    private function countUnregistered(int $year, array $settings): int
    {
        $ageExpr = $this->ageInYearSql('c', $year);
        $sql = 'SELECT COUNT(*) AS total FROM citizens c LEFT JOIN households h ON h.id=c.household_id LEFT JOIN defense_nvqs_records n ON n.citizen_id=c.id AND n.recruitment_year=:year AND n.status<>"DELETED" AND ' . $this->tenantWhere('n', 'defense_nvqs_records') . ' WHERE ' . $this->maleCondition('c') . ' AND ' . $this->activeCitizenCondition('c') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ') AND ' . $ageExpr . ' BETWEEN :from_age AND :to_age AND COALESCE(n.registered_status,"NO") <> "YES"';
        return (int) (($this->fetchOne($sql, $this->withTenant(['year' => $year, 'from_age' => (int) $settings['nvqs_registration_age'], 'to_age' => (int) $settings['nvqs_follow_end_age']])) ?: [])['total'] ?? 0);
    }

    private function countTrackingMissing(int $year, array $settings, string $doneCondition): int
    {
        $ageExpr = $this->ageInYearSql('c', $year);
        $sql = 'SELECT COUNT(*) AS total FROM citizens c LEFT JOIN households h ON h.id=c.household_id LEFT JOIN defense_nvqs_records n ON n.citizen_id=c.id AND n.recruitment_year=:year AND n.status<>"DELETED" AND ' . $this->tenantWhere('n', 'defense_nvqs_records') . ' WHERE ' . $this->maleCondition('c') . ' AND ' . $this->activeCitizenCondition('c') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ') AND ' . $ageExpr . ' BETWEEN :from_age AND :to_age AND NOT (' . $doneCondition . ')';
        return (int) (($this->fetchOne($sql, $this->withTenant(['year' => $year, 'from_age' => (int) $settings['nvqs_call_age'], 'to_age' => (int) $settings['nvqs_follow_end_age']])) ?: [])['total'] ?? 0);
    }

    private function countTable(string $table, string $condition = '1=1'): int
    {
        return (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM ' . $table . ' WHERE status<>"DELETED" AND ' . $this->tenantWhere($table) . ' AND ' . $condition, $this->withTenant()) ?: [])['total'] ?? 0);
    }

    private function settings(?int $year = null): array
    {
        $this->ensureDefaultSettings();
        $rows = $this->fetchAll('SELECT setting_key, setting_value FROM defense_security_settings WHERE ' . $this->tenantWhere('defense_security_settings') . ' AND (applied_year IS NULL OR applied_year=:year) ORDER BY applied_year DESC', $this->withTenant(['year' => $year ?: (int) date('Y')]));
        $settings = self::SETTINGS;
        foreach ($rows as $row) if (array_key_exists($row['setting_key'], $settings)) $settings[$row['setting_key']] = (int) $row['setting_value'];
        return $settings;
    }

    private function ensureDefaultSettings(): void
    {
        foreach (self::SETTINGS as $key => $value) {
            $this->execute('INSERT IGNORE INTO defense_security_settings (village_id, setting_key, setting_value, applied_year) VALUES (:village_id,:setting_key,:setting_value,NULL)', $this->withTenant(['setting_key' => $key, 'setting_value' => (string) $value]));
        }
    }

    private function normalizeNvqs(array $row): array
    {
        $base = $this->normalizeCitizen($row);
        return array_merge($base, ['id'=>(int)($row['id'] ?? 0),'citizen_id'=>(int)$row['citizen_id'],'recruitment_year'=>(int)$row['recruitment_year'],'registered_status'=>(string)$row['registered_status'],'registered_status_label'=>self::YES_NO[$row['registered_status']]??'KhÃ´ng','registration_date'=>$row['registration_date']??null,'preliminary_status'=>(string)$row['preliminary_status'],'preliminary_status_label'=>self::PRELIMINARY[$row['preliminary_status']]??'ChÆ°a cáº­p nháº­t','preliminary_date'=>$row['preliminary_date']??null,'medical_exam_status'=>(string)$row['medical_exam_status'],'medical_exam_status_label'=>self::MEDICAL[$row['medical_exam_status']]??'ChÆ°a cáº­p nháº­t','medical_exam_date'=>$row['medical_exam_date']??null,'health_classification'=>(string)($row['health_classification']??''),'eligibility_status'=>(string)$row['eligibility_status'],'eligibility_status_label'=>self::ELIGIBILITY[$row['eligibility_status']]??'ChÆ°a xÃ¡c Ä‘á»‹nh','deferment_reason'=>(string)($row['deferment_reason']??''),'exemption_reason'=>(string)($row['exemption_reason']??''),'selection_status'=>(string)$row['selection_status'],'selection_status_label'=>self::SELECTION[$row['selection_status']]??'ChÆ°a trÃºng tuyá»ƒn','order_received'=>(bool)$row['order_received'],'enlistment_date'=>$row['enlistment_date']??null,'enlistment_unit'=>(string)($row['enlistment_unit']??''),'active_service'=>(bool)$row['active_service'],'discharge_date'=>$row['discharge_date']??null,'discharge_unit'=>(string)($row['discharge_unit']??''),'completed_service'=>(bool)$row['completed_service'],'note'=>(string)($row['note']??'')]);
    }

    private function normalizeMilitia(array $row): array
    {
        return array_merge($this->normalizeCitizen($row), ['id'=>(int)$row['id'],'citizen_id'=>(int)$row['citizen_id'],'militia_type'=>(string)$row['militia_type'],'militia_type_label'=>self::MILITIA_TYPES[$row['militia_type']]??'KhÃ¡c','position_name'=>(string)($row['position_name']??''),'unit_name'=>(string)($row['unit_name']??''),'joined_date'=>$row['joined_date']??null,'ended_date'=>$row['ended_date']??null,'training_name'=>(string)($row['training_name']??''),'training_date'=>$row['training_date']??null,'training_result'=>(string)($row['training_result']??''),'participation_status'=>(string)$row['participation_status'],'participation_status_label'=>self::PARTICIPATION[$row['participation_status']]??'Äang tham gia','reason'=>(string)($row['reason']??''),'note'=>(string)($row['note']??'')]);
    }

    private function normalizeSecurityForce(array $row): array
    {
        return array_merge($this->normalizeCitizen($row), ['id'=>(int)$row['id'],'citizen_id'=>(int)$row['citizen_id'],'team_name'=>(string)$row['team_name'],'position_code'=>(string)$row['position_code'],'position_label'=>self::SECURITY_POSITIONS[$row['position_code']]??'Tá»• viÃªn','joined_date'=>$row['joined_date']??null,'ended_date'=>$row['ended_date']??null,'area_in_charge'=>(string)($row['area_in_charge']??''),'participation_status'=>(string)$row['participation_status'],'participation_status_label'=>self::SECURITY_STATUS[$row['participation_status']]??'Äang hoáº¡t Ä‘á»™ng','reason'=>(string)($row['reason']??''),'note'=>(string)($row['note']??'')]);
    }

    private function normalizeCitizen(array $row): array
    {
        return ['id'=>(int)($row['id'] ?? $row['citizen_id'] ?? 0),'citizen_id'=>(int)($row['citizen_id'] ?? $row['id'] ?? 0),'citizen_code'=>(string)($row['citizen_code']??''),'full_name'=>(string)($row['full_name']??''),'date_of_birth'=>$row['date_of_birth']??null,'gender'=>(string)($row['gender']??''),'household_code'=>(string)($row['household_code']??''),'head_citizen_name'=>(string)($row['head_citizen_name']??''),'address'=>(string)($row['address']??''),'area_code'=>(string)($row['area_code']??''),'kpi_reason'=>(string)($row['kpi_reason']??'')];
    }

    private function citizenNvqsWarnings(array $citizen, int $year): array
    {
        $settings = $this->settings($year);
        $age = $this->ageFromDate((string) ($citizen['date_of_birth'] ?? ''), $year);
        $warnings = [];
        if (!$this->isMale((string) ($citizen['gender'] ?? '')) || $age === null) return $warnings;
        if ($age === (int) $settings['nvqs_warning_age']) $warnings[] = 'Sáº¯p Ä‘áº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS';
        if ($age === (int) $settings['nvqs_registration_age']) $warnings[] = 'Äáº¿n tuá»•i Ä‘Äƒng kÃ½ NVQS';
        if ($age >= (int) $settings['nvqs_call_age'] && $age <= (int) $settings['nvqs_follow_end_age']) $warnings[] = 'Trong Ä‘á»™ tuá»•i cáº§n theo dÃµi tuyá»ƒn quÃ¢n';
        if ($warnings && !$this->existingNvqs((int) $citizen['id'], $year)) $warnings[] = 'ChÆ°a cÃ³ há»“ sÆ¡ NVQS';
        return $warnings;
    }

    private function mergeCitizenSearchRows(array $rows, string $query, int $limit): array
    {
        $seen = [];
        foreach ($rows as $row) $seen[(int) $row['id']] = true;
        $needle = $this->normalizeSearchText($query);
        if ($needle === '') return $rows;
        $candidates = $this->fetchAll('SELECT c.id, c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.address, h.area_code FROM citizens c LEFT JOIN households h ON h.id=c.household_id WHERE ' . $this->activeCitizenCondition('c') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND (h.id IS NULL OR ' . $this->tenantWhere('h', 'households') . ') ORDER BY c.full_name ASC LIMIT 1000', $this->withTenant());
        foreach ($candidates as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) continue;
            $haystack = $this->normalizeSearchText(implode(' ', [$row['citizen_code'] ?? '', $row['full_name'] ?? '', $row['household_code'] ?? '', $row['address'] ?? '', $row['area_code'] ?? '']));
            if ($haystack !== '' && str_contains($haystack, $needle)) { $rows[] = $row; $seen[$id] = true; if (count($rows) >= $limit) break; }
        }
        return $rows;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $groups = [
            'a' => '/[\x{00E0}\x{00E1}\x{1EA1}\x{1EA3}\x{00E3}\x{00E2}\x{1EA7}\x{1EA5}\x{1EAD}\x{1EA9}\x{1EAB}\x{0103}\x{1EB1}\x{1EAF}\x{1EB7}\x{1EB3}\x{1EB5}]/u',
            'e' => '/[\x{00E8}\x{00E9}\x{1EB9}\x{1EBB}\x{1EBD}\x{00EA}\x{1EC1}\x{1EBF}\x{1EC7}\x{1EC3}\x{1EC5}]/u',
            'i' => '/[\x{00EC}\x{00ED}\x{1ECB}\x{1EC9}\x{0129}]/u',
            'o' => '/[\x{00F2}\x{00F3}\x{1ECD}\x{1ECF}\x{00F5}\x{00F4}\x{1ED3}\x{1ED1}\x{1ED9}\x{1ED5}\x{1ED7}\x{01A1}\x{1EDD}\x{1EDB}\x{1EE3}\x{1EDF}\x{1EE1}]/u',
            'u' => '/[\x{00F9}\x{00FA}\x{1EE5}\x{1EE7}\x{0169}\x{01B0}\x{1EEB}\x{1EE9}\x{1EF1}\x{1EED}\x{1EEF}]/u',
            'y' => '/[\x{1EF3}\x{00FD}\x{1EF5}\x{1EF7}\x{1EF9}]/u',
            'd' => '/[\x{0111}]/u',
        ];
        foreach ($groups as $ascii => $pattern) $value = (string) preg_replace($pattern, $ascii, $value);
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function activeCitizenCondition(string $alias): string
    {
        return "$alias.status NOT IN ('DELETED','INACTIVE') AND COALESCE($alias.life_status,'ALIVE') NOT IN ('DECEASED','DEAD')";
    }

    private function maleCondition(string $alias): string
    {
        return "(LOWER($alias.gender)='nam' OR LOWER($alias.gender)='male' OR UPPER($alias.gender)='M')";
    }

    private function ageInYearSql(string $alias, int $year): string
    {
        return '(' . $year . ' - YEAR(' . $alias . '.date_of_birth))';
    }

    private function isMale(string $gender): bool
    {
        $gender = mb_strtolower(trim($gender), 'UTF-8');
        return in_array($gender, ['nam','male','m'], true);
    }

    private function ageFromDate(string $date, int $year): ?int
    {
        return preg_match('/^(\d{4})-/', $date, $m) ? $year - (int) $m[1] : null;
    }

    private function year(array $filters): int
    {
        $year = (int) ($filters['year'] ?? $filters['report_year'] ?? date('Y'));
        return $year > 1900 && $year < 2200 ? $year : (int) date('Y');
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        $value = strtoupper(trim((string) $value));
        return array_key_exists($value, $allowed) ? $value : $default;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function bool(mixed $value): int
    {
        return in_array($value, [1, '1', true, 'true', 'TRUE', 'on', 'YES', 'yes'], true) ? 1 : 0;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        throw new RuntimeException('NgÃ y pháº£i theo Ä‘á»‹nh dáº¡ng dd/mm/yyyy');
    }

    private function pairs(array $map): array
    {
        return array_map(fn($key, $label) => ['value' => $key, 'label' => $label], array_keys($map), $map);
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title'=>$title,'headers'=>$headers,'rows'=>$rows,'totalRows'=>count($rows),'filters'=>$filters,'generatedAt'=>date('c'),'meta'=>['unit'=>'ThÃ´n','report_year'=>$this->year($filters)]];
    }

    private function forceReport(string $title, array $rows, string $type, array $filters): array
    {
        $headers = $type === 'security' ? ['MÃ£ NK','Há» tÃªn','MÃ£ há»™','Tá»• ANTT','Chá»©c vá»¥','NgÃ y tham gia','NgÃ y káº¿t thÃºc','Khu vá»±c phá»¥ trÃ¡ch','Tráº¡ng thÃ¡i','Ghi chÃº'] : ['MÃ£ NK','Há» tÃªn','MÃ£ há»™','Loáº¡i dÃ¢n quÃ¢n','Chá»©c vá»¥','ÄÆ¡n vá»‹/tá»•','NgÃ y tham gia','Huáº¥n luyá»‡n','Káº¿t quáº£','Tráº¡ng thÃ¡i','Ghi chÃº'];
        $body = $type === 'security' ? array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['household_code'],$r['team_name'],$r['position_label'],$r['joined_date'],$r['ended_date'],$r['area_in_charge'],$r['participation_status_label'],$r['note']], $rows) : array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['household_code'],$r['militia_type_label'],$r['position_name'],$r['unit_name'],$r['joined_date'],$r['training_name'],$r['training_result'],$r['participation_status_label'],$r['note']], $rows);
        return $this->table($title, $headers, $body, $filters);
    }
}

