<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;
use Throwable;

final class PolicySubject extends BaseModel
{
    public const RECORD_STATUSES = [
        'ACTIVE' => 'Äang hÆ°á»Ÿng',
        'PAUSED' => 'Táº¡m dá»«ng',
        'ENDED' => 'ÄÃ£ káº¿t thÃºc',
        'DELETED' => 'ÄÃ£ xÃ³a',
    ];

    public const DEFAULT_TYPES = [
        'MERITORIOUS_PERSON' => 'NgÆ°á»i cÃ³ cÃ´ng',
        'WOUNDED_SOLDIER' => 'ThÆ°Æ¡ng binh',
        'SICK_SOLDIER' => 'Bá»‡nh binh',
        'MARTYR' => 'Liá»‡t sÄ©',
        'MARTYR_RELATIVE' => 'ThÃ¢n nhÃ¢n liá»‡t sÄ©',
        'CHEMICAL_WARFARE_VICTIM' => 'NgÆ°á»i hoáº¡t Ä‘á»™ng khÃ¡ng chiáº¿n bá»‹ nhiá»…m cháº¥t Ä‘á»™c hÃ³a há»c',
        'SOCIAL_ASSISTANCE' => 'Äá»‘i tÆ°á»£ng báº£o trá»£ xÃ£ há»™i',
        'DISABLED_PERSON' => 'NgÆ°á»i khuyáº¿t táº­t',
    ];

    public function ensureSchema(): void
    {
        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS policy_subject_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_policy_subject_types_village_code (village_id, code),
  KEY idx_policy_subject_types_village_active (village_id, is_active, deleted_at),
  KEY idx_policy_subject_types_order (village_id, display_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS citizen_policy_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  citizen_id BIGINT UNSIGNED NOT NULL,
  policy_type_id BIGINT UNSIGNED NOT NULL,
  benefit_level VARCHAR(160) NULL,
  decision_number VARCHAR(120) NULL,
  decision_date DATE NULL,
  issuing_authority VARCHAR(180) NULL,
  benefit_start_date DATE NOT NULL,
  benefit_end_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_citizen_policy_village_type_status (village_id, policy_type_id, status),
  KEY idx_citizen_policy_citizen_type (village_id, citizen_id, policy_type_id, status),
  KEY idx_citizen_policy_dates (village_id, benefit_start_date, benefit_end_date),
  CONSTRAINT fk_citizen_policy_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE RESTRICT,
  CONSTRAINT fk_citizen_policy_type FOREIGN KEY (policy_type_id) REFERENCES policy_subject_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS policy_subject_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  file_type VARCHAR(60) NOT NULL DEFAULT 'OTHER',
  original_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  deleted_by BIGINT UNSIGNED NULL,
  KEY idx_policy_attachments_record (village_id, record_id, deleted_at),
  CONSTRAINT fk_policy_attachments_record FOREIGN KEY (record_id) REFERENCES citizen_policy_records(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->execute(<<<SQL
CREATE TABLE IF NOT EXISTS policy_subject_change_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  village_id BIGINT UNSIGNED NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  citizen_id BIGINT UNSIGNED NULL,
  policy_type_id BIGINT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_policy_logs_record (village_id, record_id),
  KEY idx_policy_logs_citizen (village_id, citizen_id),
  KEY idx_policy_logs_created (village_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->seedDefaultTypes();
    }

    public function catalogs(): array
    {
        $this->ensureSchema();
        return [
            'record_statuses' => $this->options(self::RECORD_STATUSES, ['DELETED']),
            'policy_types' => array_map(fn(array $row) => [
                'value' => (string) $row['id'],
                'label' => $row['name'],
                'code' => $row['code'],
            ], $this->typeList(['pageSize' => 200, 'active' => 1])['items']),
            'areas' => $this->areaOptions(),
            'genders' => [
                ['value' => 'Nam', 'label' => 'Nam'],
                ['value' => 'Ná»¯', 'label' => 'Ná»¯'],
                ['value' => 'KhÃ¡c', 'label' => 'KhÃ¡c'],
            ],
        ];
    }

    public function typeList(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 50));
        $where = ['pst.deleted_at IS NULL', $this->tenantWhere('pst', 'policy_subject_types')];
        $params = $this->withTenant();
        if (($filters['active'] ?? '') !== '') {
            $where[] = 'pst.is_active=:active';
            $params['active'] = (int) $filters['active'];
        }
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(pst.code) LIKE :q OR LOWER(pst.name) LIKE :q OR LOWER(COALESCE(pst.description,"")) LIKE :q)';
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM policy_subject_types pst $sqlWhere", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT pst.* FROM policy_subject_types pst $sqlWhere ORDER BY pst.display_order ASC, pst.name ASC LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn(array $row) => $this->normalizeType($row), $rows), $page, $pageSize, $total);
    }

    public function saveType(array $data, int $userId, ?int $id = null): array
    {
        $this->ensureSchema();
        $params = [
            'code' => $this->code($data['code'] ?? $data['name'] ?? ''),
            'name' => $this->required($data['name'] ?? null, 'TÃªn loáº¡i Ä‘á»‘i tÆ°á»£ng'),
            'description' => $this->nullable($data['description'] ?? null),
            'display_order' => (int) ($data['display_order'] ?? $data['displayOrder'] ?? 0),
            'is_active' => empty($data['is_active']) && ($data['isActive'] ?? null) === false ? 0 : (int) ($data['is_active'] ?? $data['isActive'] ?? 1),
            'user' => $userId,
        ];
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE policy_subject_types SET code=:code, name=:name, description=:description, display_order=:display_order, is_active=:is_active, updated_by=:user WHERE id=:id AND deleted_at IS NULL AND ' . $this->tenantWhere('policy_subject_types'), $this->withTenant($params));
            return $this->findType($id) ?: [];
        }
        $columns = ['code','name','description','display_order','is_active','created_by','updated_by'];
        $insert = $params + ['created_by' => $userId, 'updated_by' => $userId];
        $this->addTenantInsert('policy_subject_types', $columns, $insert);
        $newId = $this->insert('INSERT INTO policy_subject_types (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ') ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), display_order=VALUES(display_order), is_active=VALUES(is_active), deleted_at=NULL, updated_by=VALUES(updated_by)', $insert);
        if ($newId <= 0) {
            $row = $this->fetchOne('SELECT id FROM policy_subject_types WHERE code=:code AND ' . $this->tenantWhere('policy_subject_types'), $this->withTenant(['code' => $params['code']]));
            $newId = (int) ($row['id'] ?? 0);
        }
        return $this->findType($newId) ?: [];
    }

    public function findType(int $id): ?array
    {
        $this->ensureSchema();
        $row = $this->fetchOne('SELECT * FROM policy_subject_types WHERE id=:id AND deleted_at IS NULL AND ' . $this->tenantWhere('policy_subject_types'), $this->withTenant(['id' => $id]));
        return $row ? $this->normalizeType($row) : null;
    }

    public function deleteType(int $id, int $userId): void
    {
        $this->ensureSchema();
        $used = (int) (($this->fetchOne('SELECT COUNT(*) AS total FROM citizen_policy_records WHERE policy_type_id=:id AND status<>"DELETED" AND ' . $this->tenantWhere('citizen_policy_records'), $this->withTenant(['id' => $id])) ?: [])['total'] ?? 0);
        if ($used > 0) throw new RuntimeException('KhÃ´ng thá»ƒ xÃ³a loáº¡i Ä‘á»‘i tÆ°á»£ng Ä‘Ã£ cÃ³ há»“ sÆ¡');
        $this->execute('UPDATE policy_subject_types SET deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('policy_subject_types'), $this->withTenant(['id' => $id, 'user' => $userId]));
    }

    public function recordList(array $filters): array
    {
        $this->ensureSchema();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->recordWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM citizen_policy_records cpr INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id INNER JOIN citizens c ON c.id=cpr.citizen_id INNER JOIN households h ON h.id=c.household_id $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll($this->recordSelect() . " $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn(array $row) => $this->normalizeRecord($row), $rows), $page, $pageSize, $total);
    }

    public function findRecord(int $id, bool $includeDeleted = false): ?array
    {
        if (!$this->db->inTransaction()) $this->ensureSchema();
        $where = ['cpr.id=:id', $this->tenantWhere('cpr', 'citizen_policy_records'), $this->tenantWhere('pst', 'policy_subject_types'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        if (!$includeDeleted) $where[] = 'cpr.status <> "DELETED"';
        $row = $this->fetchOne($this->recordSelect() . ' WHERE ' . implode(' AND ', $where), $this->withTenant(['id' => $id]));
        if (!$row) return null;
        $record = $this->normalizeRecord($row);
        $record['attachments'] = $this->attachments($record['id']);
        $record['history'] = $this->recordHistory($record['id']);
        return $record;
    }

    public function createRecord(array $data, int $userId, array $requestMeta = []): array
    {
        $this->ensureSchema();
        $params = $this->recordParams($data, $userId);
        $this->validateCitizen((int) $params['citizen_id']);
        $this->validateType((int) $params['policy_type_id']);
        try {
            $this->db->beginTransaction();
            $closed = $this->closeCurrentRecord((int) $params['citizen_id'], (int) $params['policy_type_id'], $params['benefit_start_date'], $userId, $requestMeta);
            $columns = ['citizen_id','policy_type_id','benefit_level','decision_number','decision_date','issuing_authority','benefit_start_date','benefit_end_date','status','note','created_by','updated_by'];
            $insert = $params + ['created_by' => $userId, 'updated_by' => $userId];
            $this->addTenantInsert('citizen_policy_records', $columns, $insert);
            $newId = $this->insert('INSERT INTO citizen_policy_records (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $insert);
            $row = $this->findRecord($newId, true);
            $this->writeChangeLog('create', $row, null, $row, $userId, $requestMeta);
            foreach ($closed as $item) $this->writeChangeLog('end_active', $item['after'], $item['before'], $item['after'], $userId, $requestMeta);
            $this->db->commit();
            return $row ?: [];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function updateRecord(int $id, array $data, int $userId, array $requestMeta = []): array
    {
        $this->ensureSchema();
        $before = $this->findRecord($id);
        if (!$before) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ chÃ­nh sÃ¡ch');
        $params = $this->recordParams($data + $before, $userId, $before);
        $this->validateCitizen((int) $params['citizen_id']);
        $this->validateType((int) $params['policy_type_id']);
        if ((int) $params['citizen_id'] !== (int) $before['citizen_id'] || (int) $params['policy_type_id'] !== (int) $before['policy_type_id']) {
            $newRecord = $this->createRecord($params, $userId, $requestMeta);
            $endDate = date('Y-m-d', strtotime($params['benefit_start_date'] . ' -1 day'));
            if ($endDate < (string) $before['benefit_start_date']) $endDate = (string) $before['benefit_start_date'];
            $this->execute('UPDATE citizen_policy_records SET status="ENDED", benefit_end_date=:end_date, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizen_policy_records'), $this->withTenant(['id' => $id, 'end_date' => $endDate, 'user' => $userId]));
            $closed = $this->findRecord($id, true) ?: [];
            $this->writeChangeLog('transfer', $closed, $before, $closed, $userId, $requestMeta);
            return $newRecord;
        }
        $params['id'] = $id;
        $this->execute('UPDATE citizen_policy_records SET benefit_level=:benefit_level, decision_number=:decision_number, decision_date=:decision_date, issuing_authority=:issuing_authority, benefit_start_date=:benefit_start_date, benefit_end_date=:benefit_end_date, status=:status, note=:note, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizen_policy_records'), $this->withTenant($params));
        $after = $this->findRecord($id, true) ?: [];
        $this->writeChangeLog('update', $after, $before, $after, $userId, $requestMeta);
        return $after;
    }

    public function deleteRecord(int $id, int $userId, array $requestMeta = []): void
    {
        $this->ensureSchema();
        $before = $this->findRecord($id);
        if (!$before) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ chÃ­nh sÃ¡ch');
        $this->execute('UPDATE citizen_policy_records SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizen_policy_records'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $this->writeChangeLog('delete', ['id' => $id] + $before, $before, null, $userId, $requestMeta);
    }

    public function citizenSummary(int $citizenId): array
    {
        $this->ensureSchema();
        $this->validateCitizen($citizenId);
        $rows = $this->fetchAll(
            $this->recordSelect() . ' WHERE cpr.citizen_id=:citizen_id AND cpr.status IN ("ACTIVE","PAUSED") AND cpr.deleted_at IS NULL AND ' . $this->tenantWhere('cpr', 'citizen_policy_records') . ' AND ' . $this->tenantWhere('pst', 'policy_subject_types') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY pst.display_order ASC, pst.name ASC, cpr.benefit_start_date DESC',
            $this->withTenant(['citizen_id' => $citizenId])
        );
        return array_map(fn(array $row) => $this->normalizeRecord($row), $rows);
    }

    public function searchCitizens(string $query, int $limit = 12): array
    {
        $this->ensureSchema();
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];
        $q = '%' . mb_strtolower($query, 'UTF-8') . '%';
        $digits = preg_replace('/\\D+/', '', $query) ?? '';
        $qDigits = $digits !== '' ? '%' . $digits . '%' : ''; 
        $rows = $this->fetchAll(
            'SELECT c.id, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, h.household_code, h.address, h.area_code
             FROM citizens c INNER JOIN households h ON h.id=c.household_id
             WHERE c.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")
               AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households') . '
               AND (LOWER(c.citizen_code) LIKE :q OR LOWER(c.full_name) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR (:q_digits <> "" AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(c.identity_number,"")," ",""),"-",""),".",""),"/","") LIKE :q_digits) OR LOWER(h.household_code) LIKE :q)
             ORDER BY h.household_code ASC, c.full_name ASC LIMIT ' . max(1, min(30, $limit)),
            $this->withTenant(['q' => $q, 'q_digits' => $qDigits])
        );
        return array_map(fn(array $row) => [
            'id' => (int) $row['id'],
            'citizen_code' => (string) ($row['citizen_code'] ?? ''),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'gender' => (string) ($row['gender'] ?? ''),
            'date_of_birth' => (string) ($row['date_of_birth'] ?? ''),
            'identity_number' => (string) ($row['identity_number'] ?? ''),
            'household_code' => (string) ($row['household_code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'area_code' => (string) ($row['area_code'] ?? ''),
        ], $rows);
    }

    public function dashboard(array $filters): array
    {
        $this->ensureSchema();
        [$where, $params] = $this->recordWhere($filters, false);
        $rows = $this->fetchAll(
            "SELECT pst.code, pst.name, COUNT(DISTINCT cpr.citizen_id) AS total
             FROM citizen_policy_records cpr
             INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id
             INNER JOIN citizens c ON c.id=cpr.citizen_id
             INNER JOIN households h ON h.id=c.household_id $where AND cpr.status='ACTIVE'
             GROUP BY pst.code, pst.name
             ORDER BY pst.display_order ASC, pst.name ASC",
            $params
        );
        $metrics = [];
        foreach (self::DEFAULT_TYPES as $code => $label) $metrics[$code] = ['code' => $code, 'label' => $label, 'total' => 0];
        foreach ($rows as $row) $metrics[$row['code']] = ['code' => $row['code'], 'label' => $row['name'], 'total' => (int) $row['total']];
        $trend = $this->fetchAll(
            "SELECT YEAR(cpr.benefit_start_date) AS year, COUNT(*) AS total
             FROM citizen_policy_records cpr
             INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id
             INNER JOIN citizens c ON c.id=cpr.citizen_id
             INNER JOIN households h ON h.id=c.household_id $where
             GROUP BY YEAR(cpr.benefit_start_date)
             ORDER BY year ASC",
            $params
        );
        return [
            'metrics' => array_values($metrics),
            'trend' => array_map(fn(array $row) => ['year' => (int) $row['year'], 'total' => (int) $row['total']], $trend),
        ];
    }

    public function report(array $filters): array
    {
        $this->ensureSchema();
        [$where, $params, $order] = $this->recordWhere($filters);
        $items = array_map(fn(array $row) => $this->normalizeRecord($row), $this->fetchAll($this->recordSelect() . " $where $order", $params));
        $dashboard = $this->dashboard($filters);
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                $index + 1,
                $item['citizen_code'],
                $item['full_name'],
                $item['household_code'],
                $item['area_code'],
                $item['gender'],
                $item['age'],
                $item['policy_type_name'],
                $item['benefit_level'],
                $item['decision_number'],
                $this->date($item['decision_date']),
                $item['issuing_authority'],
                $this->date($item['benefit_start_date']),
                $this->date($item['benefit_end_date']),
                $item['status_label'],
                $item['note'],
            ];
        }
        $summary = [];
        foreach ($dashboard['metrics'] as $metric) $summary[$metric['label']] = $metric['total'];
        return [
            'title' => 'BÃ¡o cÃ¡o Ä‘á»‘i tÆ°á»£ng chÃ­nh sÃ¡ch',
            'headers' => ['STT','MÃ£ nhÃ¢n kháº©u','Há» tÃªn','MÃ£ há»™','Khu','Giá»›i tÃ­nh','Tuá»•i','Loáº¡i Ä‘á»‘i tÆ°á»£ng','Má»©c hÆ°á»Ÿng','Sá»‘ quyáº¿t Ä‘á»‹nh','NgÃ y quyáº¿t Ä‘á»‹nh','CÆ¡ quan ban hÃ nh','Báº¯t Ä‘áº§u hÆ°á»Ÿng','Káº¿t thÃºc','Tráº¡ng thÃ¡i','Ghi chÃº'],
            'rows' => $rows,
            'items' => $items,
            'totalRows' => count($rows),
            'summary' => $summary,
            'metrics' => $dashboard['metrics'],
            'trend' => $dashboard['trend'],
            'filters' => $filters,
            'generatedAt' => date('c'),
        ];
    }

    public function addAttachment(int $recordId, array $file, string $fileType, int $userId, array $requestMeta = []): array
    {
        $this->ensureSchema();
        $record = $this->findRecord($recordId);
        if (!$record) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y há»“ sÆ¡ chÃ­nh sÃ¡ch');
        $stored = $this->storeUpload($file);
        $columns = ['record_id','file_type','original_name','file_path','mime_type','file_size','uploaded_by'];
        $params = [
            'record_id' => $recordId,
            'file_type' => $this->fileType($fileType),
            'original_name' => mb_substr((string) ($file['name'] ?? ''), 0, 255),
            'file_path' => $stored['file_path'],
            'mime_type' => $stored['mime_type'],
            'file_size' => (int) ($file['size'] ?? 0),
            'uploaded_by' => $userId,
        ];
        $this->addTenantInsert('policy_subject_attachments', $columns, $params);
        $id = $this->insert('INSERT INTO policy_subject_attachments (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        $row = $this->attachment($id) ?: [];
        $this->writeChangeLog('upload', $record, null, ['attachment' => $row], $userId, $requestMeta);
        return $row;
    }

    public function deleteAttachment(int $id, int $userId, array $requestMeta = []): void
    {
        $this->ensureSchema();
        $before = $this->attachment($id);
        if (!$before) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y file Ä‘Ã­nh kÃ¨m');
        $this->execute('UPDATE policy_subject_attachments SET deleted_at=NOW(), deleted_by=:user WHERE id=:id AND ' . $this->tenantWhere('policy_subject_attachments'), $this->withTenant(['id' => $id, 'user' => $userId]));
        $this->writeChangeLog('delete_attachment', ['id' => $before['record_id']], ['attachment' => $before], null, $userId, $requestMeta);
    }

    public function attachments(int $recordId): array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureSchema();
        }
        $rows = $this->fetchAll('SELECT * FROM policy_subject_attachments WHERE record_id=:record_id AND deleted_at IS NULL AND ' . $this->tenantWhere('policy_subject_attachments') . ' ORDER BY created_at DESC, id DESC', $this->withTenant(['record_id' => $recordId]));
        return array_map(fn(array $row) => $this->normalizeAttachment($row), $rows);
    }

    private function recordWhere(array $filters, bool $withOrder = true): array
    {
        $where = ['cpr.status <> "DELETED"', 'cpr.deleted_at IS NULL', 'pst.deleted_at IS NULL', 'c.status <> "DELETED"', 'h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE")', $this->tenantWhere('cpr', 'citizen_policy_records'), $this->tenantWhere('pst', 'policy_subject_types'), $this->tenantWhere('c', 'citizens'), $this->tenantWhere('h', 'households')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $params['q'] = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $where[] = '(LOWER(c.citizen_code) LIKE :q OR LOWER(c.full_name) LIKE :q OR LOWER(COALESCE(c.identity_number,"")) LIKE :q OR LOWER(h.household_code) LIKE :q OR LOWER(COALESCE(cpr.decision_number,"")) LIKE :q OR LOWER(pst.name) LIKE :q)';
        }
        $typeId = (int) ($filters['policy_type_id'] ?? $filters['policyTypeId'] ?? $filters['type'] ?? 0);
        if ($typeId > 0) {
            $where[] = 'cpr.policy_type_id=:policy_type_id';
            $params['policy_type_id'] = $typeId;
        }
        $status = strtoupper(trim((string) ($filters['record_status'] ?? $filters['status'] ?? '')));
        if (isset(self::RECORD_STATUSES[$status]) && $status !== 'DELETED') {
            $where[] = 'cpr.status=:record_status';
            $params['record_status'] = $status;
        }
        foreach (['area_code' => 'h.area_code', 'gender' => 'c.gender'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? $filters[str_replace('_', '', $key)] ?? ''));
            if ($value !== '') {
                $where[] = $column . '=:' . $key;
                $params[$key] = $value;
            }
        }
        if (!empty($filters['household_id']) || !empty($filters['householdId'])) {
            $where[] = 'h.id=:household_id';
            $params['household_id'] = (int) ($filters['household_id'] ?? $filters['householdId']);
        }
        if (!empty($filters['age_from']) || !empty($filters['ageFrom'])) {
            $where[] = 'TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) >= :age_from';
            $params['age_from'] = (int) ($filters['age_from'] ?? $filters['ageFrom']);
        }
        if (!empty($filters['age_to']) || !empty($filters['ageTo'])) {
            $where[] = 'TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) <= :age_to';
            $params['age_to'] = (int) ($filters['age_to'] ?? $filters['ageTo']);
        }
        $result = ['WHERE ' . implode(' AND ', $where), $params];
        if ($withOrder) $result[] = $this->listOrder($filters, ['citizen_code' => 'c.citizen_code', 'full_name' => 'c.full_name', 'household_code' => 'h.household_code', 'policy_type' => 'pst.name', 'start_date' => 'cpr.benefit_start_date', 'status' => 'cpr.status'], 'start_date', 'DESC', ['cpr.id DESC']);
        return $result;
    }

    private function recordSelect(): string
    {
        return 'SELECT cpr.*, pst.code AS policy_type_code, pst.name AS policy_type_name, c.citizen_code, c.full_name, c.gender, c.date_of_birth, c.identity_number, h.household_code, h.address AS household_address, h.area_code,
            TIMESTAMPDIFF(YEAR, c.date_of_birth, CURDATE()) AS age
            FROM citizen_policy_records cpr
            INNER JOIN policy_subject_types pst ON pst.id=cpr.policy_type_id
            INNER JOIN citizens c ON c.id=cpr.citizen_id
            INNER JOIN households h ON h.id=c.household_id';
    }

    private function recordParams(array $data, int $userId, ?array $existing = null): array
    {
        $status = strtoupper(trim((string) ($data['status'] ?? $existing['status'] ?? 'ACTIVE')));
        if (!isset(self::RECORD_STATUSES[$status]) || $status === 'DELETED') $status = 'ACTIVE';
        return [
            'citizen_id' => (int) ($data['citizen_id'] ?? $data['citizenId'] ?? $existing['citizen_id'] ?? 0),
            'policy_type_id' => (int) ($data['policy_type_id'] ?? $data['policyTypeId'] ?? $existing['policy_type_id'] ?? 0),
            'benefit_level' => $this->nullable($data['benefit_level'] ?? $data['benefitLevel'] ?? $existing['benefit_level'] ?? null),
            'decision_number' => $this->nullable($data['decision_number'] ?? $data['decisionNumber'] ?? $existing['decision_number'] ?? null),
            'decision_date' => $this->dateOrNull($data['decision_date'] ?? $data['decisionDate'] ?? $existing['decision_date'] ?? null),
            'issuing_authority' => $this->nullable($data['issuing_authority'] ?? $data['issuingAuthority'] ?? $existing['issuing_authority'] ?? null),
            'benefit_start_date' => $this->dateOrFail($data['benefit_start_date'] ?? $data['benefitStartDate'] ?? $existing['benefit_start_date'] ?? null, 'NgÃ y báº¯t Ä‘áº§u hÆ°á»Ÿng'),
            'benefit_end_date' => $this->dateOrNull($data['benefit_end_date'] ?? $data['benefitEndDate'] ?? $existing['benefit_end_date'] ?? null),
            'status' => $status,
            'note' => $this->nullable($data['note'] ?? $existing['note'] ?? null),
            'user' => $userId,
        ];
    }

    private function closeCurrentRecord(int $citizenId, int $typeId, string $newStartDate, int $userId, array $requestMeta): array
    {
        $rows = $this->fetchAll($this->recordSelect() . ' WHERE cpr.citizen_id=:citizen_id AND cpr.policy_type_id=:policy_type_id AND cpr.status IN ("ACTIVE","PAUSED") AND cpr.deleted_at IS NULL AND ' . $this->tenantWhere('cpr', 'citizen_policy_records') . ' AND ' . $this->tenantWhere('pst', 'policy_subject_types') . ' AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY cpr.benefit_start_date DESC, cpr.id DESC', $this->withTenant(['citizen_id' => $citizenId, 'policy_type_id' => $typeId]));
        $closed = [];
        foreach ($rows as $row) {
            $before = $this->normalizeRecord($row);
            $endDate = date('Y-m-d', strtotime($newStartDate . ' -1 day'));
            if ($endDate < (string) $row['benefit_start_date']) {
                if ($newStartDate !== (string) $row['benefit_start_date']) throw new RuntimeException('NgÃ y báº¯t Ä‘áº§u má»›i pháº£i sau ngÃ y báº¯t Ä‘áº§u cá»§a há»“ sÆ¡ hiá»‡n táº¡i');
                $endDate = (string) $row['benefit_start_date'];
            }
            $this->execute('UPDATE citizen_policy_records SET status="ENDED", benefit_end_date=:end_date, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('citizen_policy_records'), $this->withTenant(['id' => (int) $row['id'], 'end_date' => $endDate, 'user' => $userId]));
            $closed[] = ['before' => $before, 'after' => $this->findRecord((int) $row['id'], true) ?: [], 'meta' => $requestMeta];
        }
        return $closed;
    }

    private function writeChangeLog(string $action, ?array $record, ?array $before, ?array $after, int $userId, array $requestMeta): void
    {
        $columns = ['record_id','citizen_id','policy_type_id','action','before_json','after_json','actor_user_id','ip_address','user_agent'];
        $params = [
            'record_id' => $record['id'] ?? $before['id'] ?? $after['id'] ?? null,
            'citizen_id' => $record['citizen_id'] ?? $before['citizen_id'] ?? $after['citizen_id'] ?? null,
            'policy_type_id' => $record['policy_type_id'] ?? $before['policy_type_id'] ?? $after['policy_type_id'] ?? null,
            'action' => $action,
            'before_json' => $before ? json_encode($this->safeLogPayload($before), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_json' => $after ? json_encode($this->safeLogPayload($after), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'actor_user_id' => $userId,
            'ip_address' => $this->nullable($requestMeta['ip'] ?? null),
            'user_agent' => $this->nullable(mb_substr((string) ($requestMeta['user_agent'] ?? ''), 0, 255)),
        ];
        $this->addTenantInsert('policy_subject_change_logs', $columns, $params);
        $this->insert('INSERT INTO policy_subject_change_logs (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
    }

    private function recordHistory(int $recordId): array
    {
        $rows = $this->fetchAll('SELECT action, before_json, after_json, actor_user_id, ip_address, user_agent, created_at FROM policy_subject_change_logs WHERE record_id=:record_id AND ' . $this->tenantWhere('policy_subject_change_logs') . ' ORDER BY created_at DESC, id DESC LIMIT 100', $this->withTenant(['record_id' => $recordId]));
        return array_map(fn(array $row) => [
            'action' => $row['action'],
            'before' => $row['before_json'] ? json_decode($row['before_json'], true) : null,
            'after' => $row['after_json'] ? json_decode($row['after_json'], true) : null,
            'actor_user_id' => $row['actor_user_id'] ? (int) $row['actor_user_id'] : null,
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'created_at' => $row['created_at'],
        ], $rows);
    }

    private function validateCitizen(int $citizenId): void
    {
        if ($citizenId <= 0) throw new RuntimeException('NhÃ¢n kháº©u lÃ  báº¯t buá»™c');
        $row = $this->fetchOne('SELECT c.id FROM citizens c INNER JOIN households h ON h.id=c.household_id WHERE c.id=:id AND c.status <> "DELETED" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('c', 'citizens') . ' AND ' . $this->tenantWhere('h', 'households'), $this->withTenant(['id' => $citizenId]));
        if (!$row) throw new RuntimeException('KhÃ´ng tÃ¬m tháº¥y nhÃ¢n kháº©u');
    }

    private function validateType(int $typeId): void
    {
        if ($typeId <= 0 || !$this->findType($typeId)) throw new RuntimeException('Loáº¡i Ä‘á»‘i tÆ°á»£ng khÃ´ng há»£p lá»‡');
    }

    private function seedDefaultTypes(): void
    {
        $order = 10;
        foreach (self::DEFAULT_TYPES as $code => $name) {
            $exists = $this->fetchOne('SELECT id FROM policy_subject_types WHERE code=:code AND ' . $this->tenantWhere('policy_subject_types'), $this->withTenant(['code' => $code]));
            if ($exists) continue;
            $columns = ['code','name','display_order','is_active'];
            $params = ['code' => $code, 'name' => $name, 'display_order' => $order, 'is_active' => 1];
            $this->addTenantInsert('policy_subject_types', $columns, $params);
            $this->insert('INSERT INTO policy_subject_types (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
            $order += 10;
        }
    }

    private function storeUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new RuntimeException('File upload khÃ´ng há»£p lá»‡');
        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > 20 * 1024 * 1024) throw new RuntimeException('File tá»‘i Ä‘a 20MB');
        $original = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
        $allowed = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'webp' => ['image/webp'], 'doc' => ['application/msword','application/octet-stream'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'], 'xls' => ['application/vnd.ms-excel','application/octet-stream'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream']];
        if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) throw new RuntimeException('Äá»‹nh dáº¡ng file chÆ°a Ä‘Æ°á»£c há»— trá»£');
        $dir = dirname(__DIR__, 2) . '/uploads/policy-subjects/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('KhÃ´ng táº¡o Ä‘Æ°á»£c thÆ° má»¥c upload');
        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('KhÃ´ng lÆ°u Ä‘Æ°á»£c file upload');
        return ['file_path' => 'uploads/policy-subjects/' . date('Y/m') . '/' . $stored, 'mime_type' => $mime];
    }

    private function attachment(int $id): ?array
    {
        $row = $this->fetchOne('SELECT * FROM policy_subject_attachments WHERE id=:id AND deleted_at IS NULL AND ' . $this->tenantWhere('policy_subject_attachments'), $this->withTenant(['id' => $id]));
        return $row ? $this->normalizeAttachment($row) : null;
    }

    private function normalizeType(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['display_order'] = (int) ($row['display_order'] ?? 0);
        $row['is_active'] = (int) ($row['is_active'] ?? 1);
        return $row;
    }

    private function normalizeRecord(array $row): array
    {
        foreach (['id','citizen_id','policy_type_id','age'] as $key) $row[$key] = (int) ($row[$key] ?? 0);
        $row['status_label'] = self::RECORD_STATUSES[$row['status'] ?? 'ACTIVE'] ?? (string) ($row['status'] ?? '');
        return $row;
    }

    private function normalizeAttachment(array $row): array
    {
        foreach (['id','record_id','file_size'] as $key) $row[$key] = (int) ($row[$key] ?? 0);
        $row['url'] = '/' . ltrim((string) $row['file_path'], '/');
        return $row;
    }

    private function areaOptions(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT h.area_code AS value FROM households h WHERE h.area_code IS NOT NULL AND TRIM(h.area_code)<>"" AND h.status NOT IN ("DELETED","ENDED","MERGED","TRANSFERRED_OUT","MOVED_OUT","INACTIVE") AND ' . $this->tenantWhere('h', 'households') . ' ORDER BY h.area_code ASC LIMIT 100', $this->withTenant());
        return array_map(fn(array $row) => ['value' => (string) $row['value'], 'label' => (string) $row['value']], $rows);
    }

    private function safeLogPayload(array $payload): array
    {
        foreach (['password','token','secret','connection_string','db_password','database_password'] as $key) unset($payload[$key]);
        return $payload;
    }

    private function code(mixed $value): string
    {
        $code = preg_replace('/[^A-Z0-9_]/', '_', strtoupper(trim((string) $value)));
        $code = trim((string) preg_replace('/_+/', '_', $code), '_');
        if ($code === '') throw new RuntimeException('MÃ£ loáº¡i Ä‘á»‘i tÆ°á»£ng lÃ  báº¯t buá»™c');
        return mb_substr($code, 0, 80);
    }

    private function fileType(mixed $value): string
    {
        $type = preg_replace('/[^A-Z0-9_]/', '_', strtoupper(trim((string) ($value ?: 'OTHER'))));
        return mb_substr($type ?: 'OTHER', 0, 60);
    }

    private function required(mixed $value, string $label): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') throw new RuntimeException($label . ' lÃ  báº¯t buá»™c');
        return $text;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function dateOrFail(mixed $value, string $label): string
    {
        $date = $this->normalizeInputDate($value);
        if ($date === null) throw new RuntimeException($label . ' khÃ´ng há»£p lá»‡');
        return $date;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $this->normalizeInputDate($text);
    }

    private function normalizeInputDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $text : null;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $text, $m)) {
            $first = (int) $m[1]; $second = (int) $m[2]; $year = (int) $m[3];
            $month = $second > 12 ? $first : ($first > 12 ? $second : $first);
            $day = $second > 12 ? $second : ($first > 12 ? $first : $second);
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }
        return null;
    }

    private function date(?string $value): string
    {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) return '';
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    private function options(array $labels, array $exclude = []): array
    {
        return array_values(array_map(fn(string $key) => ['value' => $key, 'label' => $labels[$key]], array_filter(array_keys($labels), fn(string $key) => !in_array($key, $exclude, true))));
    }
}
