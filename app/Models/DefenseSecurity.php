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

    private const YES_NO = ['YES' => 'Có', 'NO' => 'Không'];
    private const PRELIMINARY = ['NOT_UPDATED' => 'Chưa cập nhật', 'PENDING' => 'Chờ kết quả', 'PASSED' => 'Đạt', 'FAILED' => 'Không đạt'];
    private const MEDICAL = ['NOT_UPDATED' => 'Chưa cập nhật', 'PENDING' => 'Chờ kết quả', 'PASSED' => 'Đạt', 'FAILED' => 'Không đạt'];
    private const ELIGIBILITY = ['UNKNOWN' => 'Chưa xác định', 'ELIGIBLE' => 'Đủ điều kiện', 'INELIGIBLE' => 'Không đủ điều kiện', 'DEFERRED' => 'Tạm hoãn', 'EXEMPT' => 'Miễn'];
    private const SELECTION = ['NOT_SELECTED' => 'Chưa trúng tuyển', 'SELECTED' => 'Trúng tuyển', 'ENLISTED' => 'Đã nhập ngũ'];
    private const MILITIA_TYPES = ['CORE' => 'Dân quân nòng cốt', 'MOBILE' => 'Dân quân cơ động', 'ON_SITE' => 'Dân quân tại chỗ', 'SPECIALIZED' => 'Dân quân chuyên môn', 'OTHER' => 'Khác'];
    private const PARTICIPATION = ['ACTIVE' => 'Đang tham gia', 'PAUSED' => 'Tạm nghỉ', 'COMPLETED' => 'Đã hoàn thành', 'ENDED' => 'Thôi tham gia'];
    private const SECURITY_POSITIONS = ['LEADER' => 'Tổ trưởng', 'DEPUTY' => 'Tổ phó', 'MEMBER' => 'Tổ viên'];
    private const SECURITY_STATUS = ['ACTIVE' => 'Đang hoạt động', 'PAUSED' => 'Tạm nghỉ', 'ENDED' => 'Thôi tham gia'];
    private const SECURITY_RECORD_TYPES = ['CRIMINAL_RECORD' => 'Tiền án', 'ADMINISTRATIVE_RECORD' => 'Tiền sự', 'WATCHLIST' => 'Trường hợp cần chú ý về ANTT'];
    private const SECURITY_RECORD_STATUS = ['ACTIVE' => 'Đang theo dõi', 'MONITORING' => 'Đang rà soát', 'COMPLETED' => 'Đã hoàn thành', 'CLOSED' => 'Kết thúc'];
    private const INCIDENT_STATUS = ['NEW' => 'Mới tiếp nhận', 'VERIFYING' => 'Đang xác minh', 'PROCESSING' => 'Đang xử lý', 'COORDINATING' => 'Đang phối hợp', 'TRANSFERRED' => 'Đã chuyển cơ quan có thẩm quyền', 'RESOLVED' => 'Đã giải quyết', 'CLOSED' => 'Kết thúc', 'OTHER' => 'Khác'];
    private const INCIDENT_ROLES = ['REPORTER' => 'Người trình báo', 'AFFECTED' => 'Người bị ảnh hưởng/bị hại', 'WITNESS' => 'Người chứng kiến', 'REPORTED' => 'Người bị phản ánh/nghi liên quan', 'RELATED' => 'Người liên quan', 'OTHER' => 'Khác'];
    public function ensureSchema(): void
    {
        $this->assertSchemaReady();
    }

    private function assertSchemaReady(): void
    {
        $requirements = [
            'defense_security_settings' => ['village_id', 'setting_key', 'setting_value', 'applied_year'],
            'defense_nvqs_records' => ['village_id', 'citizen_id', 'recruitment_year'],
            'defense_militia_records' => ['village_id', 'citizen_id', 'militia_type'],
            'defense_security_force_records' => ['village_id', 'citizen_id', 'team_name'],
            'defense_security_records' => ['village_id', 'citizen_id', 'record_type'],
            'defense_security_record_logs' => ['village_id', 'record_id', 'content'],
            'defense_security_incident_types' => ['village_id', 'code', 'name', 'display_order', 'is_active'],
            'defense_security_incidents' => ['village_id', 'incident_code', 'incident_type_code', 'occurred_date'],
            'defense_security_incident_people' => ['village_id', 'incident_id', 'role_code'],
            'defense_security_incident_logs' => ['village_id', 'incident_id', 'content'],
        ];

        foreach ($requirements as $table => $columns) {
            $this->assertTableColumns($table, $columns);
        }
        $this->assertDefaultSettingsReady();
        $this->assertIncidentTypesReady();
    }

    private function assertTableColumns(string $table, array $columns): void
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );
        if ((int) ($row['total'] ?? 0) !== 1) {
            throw new RuntimeException('Defense Security schema is not provisioned: missing table ' . $table);
        }
        foreach ($columns as $column) {
            if (!$this->columnExists($table, $column)) {
                throw new RuntimeException('Defense Security schema is not provisioned: missing column ' . $table . '.' . $column);
            }
        }
    }

    private function assertDefaultSettingsReady(): void
    {
        $placeholders = implode(',', array_map(fn($i) => ':setting_' . $i, range(0, count(self::SETTINGS) - 1)));
        $params = $this->withTenant();
        $i = 0;
        foreach (array_keys(self::SETTINGS) as $key) {
            $params['setting_' . $i++] = $key;
        }
        $row = $this->fetchOne(
            'SELECT COUNT(DISTINCT setting_key) AS total FROM defense_security_settings WHERE ' . $this->tenantWhere('defense_security_settings') . ' AND applied_year IS NULL AND setting_key IN (' . $placeholders . ')',
            $params
        );
        if ((int) ($row['total'] ?? 0) !== count(self::SETTINGS)) {
            throw new RuntimeException('Defense Security schema is not provisioned: missing default settings');
        }
    }

    private function assertIncidentTypesReady(): void
    {
        $codes = ['THEFT', 'PROPERTY_LOSS', 'FIGHT', 'DISTURBANCE', 'CONFLICT', 'FAMILY_CONFLICT', 'PROPERTY_DAMAGE', 'NOISE', 'SUSPICIOUS_PERSON', 'OTHER'];
        $placeholders = implode(',', array_map(fn($i) => ':incident_' . $i, range(0, count($codes) - 1)));
        $params = $this->withTenant();
        foreach ($codes as $i => $code) {
            $params['incident_' . $i] = $code;
        }
        $row = $this->fetchOne(
            'SELECT COUNT(DISTINCT code) AS total FROM defense_security_incident_types WHERE ' . $this->tenantWhere('defense_security_incident_types') . ' AND is_active=1 AND code IN (' . $placeholders . ')',
            $params
        );
        if ((int) ($row['total'] ?? 0) !== count($codes)) {
            throw new RuntimeException('Defense Security schema is not provisioned: missing incident types');
        }
    }

    public function catalogs(): array
    {
        $this->assertSchemaReady();
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
            'security_record_types' => $this->pairs(self::SECURITY_RECORD_TYPES),
            'security_record_statuses' => $this->pairs(self::SECURITY_RECORD_STATUS),
            'incident_statuses' => $this->pairs(self::INCIDENT_STATUS),
            'incident_roles' => $this->pairs(self::INCIDENT_ROLES),
            'incident_types' => $this->incidentTypePairs(),
        ];
    }

    public function dashboard(array $filters = []): array
    {
        $this->assertSchemaReady();
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
            'security_records' => $this->securityRecordDashboard(),
            'incidents' => $this->incidentDashboard($filters),
            'generatedAt' => date('c'),
        ];
    }

    public function searchCitizens(string $query, int $limit = 12): array
    {
        $this->assertSchemaReady();
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
        $this->assertSchemaReady();
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
        $this->assertSchemaReady();
        $params = $this->nvqsParams($data, $userId);
        if ($id && !$this->findNvqs($id)) throw new RuntimeException('Không tìm thấy hồ sơ NVQS');
        if (!$id && $this->existingNvqs((int) $params['citizen_id'], (int) $params['recruitment_year'])) throw new RuntimeException('Nhân khẩu này đã có hồ sơ NVQS trong năm tuyển quân đã chọn.');
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

    public function paginateSecurityRecords(array $filters): array
    {
        $this->assertSchemaReady();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->securityRecordWhere($filters);
        $from = $this->personJoin('defense_security_records', 'r');
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total $from $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT r.*, " . $this->citizenSelect() . " $from $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalizeSecurityRecord($row), $rows), $page, $pageSize, $total);
    }

    public function findSecurityRecord(int $id): ?array
    {
        $this->assertSchemaReady();
        $row = $this->fetchOne('SELECT r.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_security_records', 'r') . ' WHERE r.id=:id AND r.status<>"DELETED" AND ' . $this->tenantWhere('r', 'defense_security_records') . ' AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['id' => $id]));
        if (!$row) return null;
        $data = $this->normalizeSecurityRecord($row);
        $data['logs'] = $this->securityRecordLogs($id);
        return $data;
    }

    public function saveSecurityRecord(array $data, int $userId, ?int $id = null): array
    {
        $this->assertSchemaReady();
        $params = $this->securityRecordParams($data, $userId);
        if ($id && !$this->findSecurityRecord($id)) throw new RuntimeException('Không tìm thấy hồ sơ ANTT');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE defense_security_records SET citizen_id=:citizen_id,record_type=:record_type,classification=:classification,summary=:summary,legal_basis=:legal_basis,decision_number=:decision_number,issuing_authority=:issuing_authority,occurred_date=:occurred_date,start_date=:start_date,completion_date=:completion_date,source=:source,measures=:measures,result=:result,current_status=:current_status,next_review_date=:next_review_date,note=:note,updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('defense_security_records'), $this->withTenant($params));
            return $this->findSecurityRecord($id);
        }
        $columns = array_keys($params);
        $this->addTenantInsert('defense_security_records', $columns, $params);
        $newId = $this->insert('INSERT INTO defense_security_records (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->findSecurityRecord($newId);
    }

    public function deleteSecurityRecord(int $id, int $userId): void { $this->softDelete('defense_security_records', 'findSecurityRecord', $id, $userId); }

    public function addSecurityRecordLog(int $recordId, array $data, int $userId): array
    {
        $this->assertSchemaReady();
        if (!$this->findSecurityRecord($recordId)) throw new RuntimeException('Không tìm thấy hồ sơ ANTT');
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') throw new RuntimeException('Nội dung làm việc là bắt buộc');
        $params = ['record_id' => $recordId, 'event_date' => $this->dateValue($data['event_date'] ?? date('Y-m-d')) ?: date('Y-m-d'), 'event_type' => $this->nullable($data['event_type'] ?? ''), 'content' => $content, 'result' => $this->nullable($data['result'] ?? ''), 'next_review_date' => $this->dateValue($data['next_review_date'] ?? ''), 'created_by' => $userId];
        $columns = array_keys($params);
        $this->addTenantInsert('defense_security_record_logs', $columns, $params);
        $id = $this->insert('INSERT INTO defense_security_record_logs (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        if ($params['next_review_date']) $this->execute('UPDATE defense_security_records SET next_review_date=:next_review_date, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('defense_security_records'), $this->withTenant(['next_review_date' => $params['next_review_date'], 'user' => $userId, 'id' => $recordId]));
        return $this->securityRecordLog((int) $id) ?: [];
    }

    public function paginateIncidents(array $filters): array
    {
        $this->assertSchemaReady();
        [$page, $pageSize, $offset] = $this->page((int) ($filters['page'] ?? 1), (int) ($filters['pageSize'] ?? 20));
        [$where, $params, $order] = $this->incidentWhere($filters);
        $total = (int) (($this->fetchOne("SELECT COUNT(*) AS total FROM defense_security_incidents i $where", $params) ?: [])['total'] ?? 0);
        $rows = $this->fetchAll("SELECT i.*, t.name AS incident_type_label FROM defense_security_incidents i LEFT JOIN defense_security_incident_types t ON t.code=i.incident_type_code AND t.village_id=i.village_id $where $order LIMIT $pageSize OFFSET $offset", $params);
        return $this->paginated(array_map(fn($row) => $this->normalizeIncident($row), $rows), $page, $pageSize, $total);
    }

    public function findIncident(int $id): ?array
    {
        $this->assertSchemaReady();
        $row = $this->fetchOne('SELECT i.*, t.name AS incident_type_label FROM defense_security_incidents i LEFT JOIN defense_security_incident_types t ON t.code=i.incident_type_code AND t.village_id=i.village_id WHERE i.id=:id AND i.status<>"DELETED" AND ' . $this->tenantWhere('i', 'defense_security_incidents'), $this->withTenant(['id' => $id]));
        if (!$row) return null;
        $data = $this->normalizeIncident($row);
        $data['people'] = $this->incidentPeople($id);
        $data['logs'] = $this->incidentLogs($id);
        return $data;
    }

    public function saveIncident(array $data, int $userId, ?int $id = null): array
    {
        $this->assertSchemaReady();
        $params = $this->incidentParams($data, $userId, $id);
        if ($id && !$this->findIncident($id)) throw new RuntimeException('Không tìm thấy vụ việc ANTT');
        if ($id) {
            $params['id'] = $id;
            $this->execute('UPDATE defense_security_incidents SET incident_code=:incident_code,incident_type_code=:incident_type_code,occurred_date=:occurred_date,occurred_time=:occurred_time,location=:location,area_code=:area_code,summary=:summary,asset_description=:asset_description,estimated_damage=:estimated_damage,reporter_name=:reporter_name,receiving_agency=:receiving_agency,handling_direction=:handling_direction,result=:result,incident_status=:incident_status,note=:note,updated_by=:updated_by WHERE id=:id AND ' . $this->tenantWhere('defense_security_incidents'), $this->withTenant($params));
            return $this->findIncident($id);
        }
        $columns = array_keys($params);
        $this->addTenantInsert('defense_security_incidents', $columns, $params);
        $newId = $this->insert('INSERT INTO defense_security_incidents (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->findIncident($newId);
    }

    public function deleteIncident(int $id, int $userId): void { $this->softDelete('defense_security_incidents', 'findIncident', $id, $userId); }

    public function addIncidentPerson(int $incidentId, array $data, int $userId): array
    {
        $this->assertSchemaReady();
        if (!$this->findIncident($incidentId)) throw new RuntimeException('Không tìm thấy vụ việc ANTT');
        $citizenId = (int) ($data['citizen_id'] ?? 0);
        if ($citizenId > 0 && !$this->citizenExists($citizenId)) throw new RuntimeException('Không tìm thấy nhân khẩu trong tenant hiện tại');
        $params = ['incident_id' => $incidentId, 'citizen_id' => $citizenId > 0 ? $citizenId : null, 'role_code' => $this->enum($data['role_code'] ?? 'RELATED', self::INCIDENT_ROLES, 'RELATED'), 'external_name' => $this->nullable($data['external_name'] ?? ''), 'external_contact' => $this->nullable($data['external_contact'] ?? ''), 'description' => $this->nullable($data['description'] ?? ''), 'created_by' => $userId];
        if (!$params['citizen_id'] && !$params['external_name']) throw new RuntimeException('Cần chọn nhân khẩu hoặc nhập tên người liên quan/chưa xác định');
        $columns = array_keys($params);
        $this->addTenantInsert('defense_security_incident_people', $columns, $params);
        $id = $this->insert('INSERT INTO defense_security_incident_people (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        return $this->incidentPerson((int) $id) ?: [];
    }

    public function addIncidentLog(int $incidentId, array $data, int $userId): array
    {
        $this->assertSchemaReady();
        if (!$this->findIncident($incidentId)) throw new RuntimeException('Không tìm thấy vụ việc ANTT');
        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') throw new RuntimeException('Nội dung xử lý là bắt buộc');
        $params = ['incident_id' => $incidentId, 'event_at' => $this->dateTimeValue($data['event_at'] ?? date('Y-m-d H:i:s')), 'event_type' => $this->nullable($data['event_type'] ?? ''), 'content' => $content, 'new_status' => $this->nullable($this->enum($data['new_status'] ?? '', self::INCIDENT_STATUS, '')), 'created_by' => $userId];
        $columns = array_keys($params);
        $this->addTenantInsert('defense_security_incident_logs', $columns, $params);
        $id = $this->insert('INSERT INTO defense_security_incident_logs (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')', $params);
        if ($params['new_status']) $this->execute('UPDATE defense_security_incidents SET incident_status=:incident_status, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere('defense_security_incidents'), $this->withTenant(['incident_status' => $params['new_status'], 'user' => $userId, 'id' => $incidentId]));
        return $this->incidentLog((int) $id) ?: [];
    }
    public function citizenSummary(int $citizenId): array
    {
        $this->assertSchemaReady();
        $citizen = $this->citizenExists($citizenId);
        if (!$citizen) throw new RuntimeException('Không tìm thấy nhân khẩu');
        $year = (int) date('Y');
        return [
            'citizen' => $this->normalizeCitizen($citizen),
            'nvqs' => array_map(fn($r) => $this->normalizeNvqs($r), $this->fetchAll('SELECT n.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_nvqs_records', 'n') . ' WHERE n.citizen_id=:citizen_id AND n.status<>"DELETED" AND ' . $this->tenantWhere('n', 'defense_nvqs_records') . ' ORDER BY n.recruitment_year DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'militia' => array_map(fn($r) => $this->normalizeMilitia($r), $this->fetchAll('SELECT m.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_militia_records', 'm') . ' WHERE m.citizen_id=:citizen_id AND m.status<>"DELETED" AND ' . $this->tenantWhere('m', 'defense_militia_records') . ' ORDER BY COALESCE(m.joined_date,m.created_at) DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'security_force' => array_map(fn($r) => $this->normalizeSecurityForce($r), $this->fetchAll('SELECT s.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_security_force_records', 's') . ' WHERE s.citizen_id=:citizen_id AND s.status<>"DELETED" AND ' . $this->tenantWhere('s', 'defense_security_force_records') . ' ORDER BY COALESCE(s.joined_date,s.created_at) DESC', $this->withTenant(['citizen_id' => $citizenId]))),
            'security_records' => $this->recordsByCitizen($citizenId),
            'incidents' => $this->incidentsByCitizen($citizenId),
            'warnings' => $this->citizenNvqsWarnings($citizen, $year),
        ];
    }

    public function report(string $mode, array $filters = []): array
    {
        $this->assertSchemaReady();
        $mode = str_replace('-', '_', $mode);
        if ($mode === 'summary') {
            $data = $this->dashboard($filters);
            return $this->table('Tổng quan Quốc phòng - An ninh', ['Chỉ tiêu', 'Số lượng'], [
                ['Sắp đến tuổi đăng ký NVQS', $data['nvqs']['warning_age']],
                ['Đến tuổi đăng ký NVQS', $data['nvqs']['registration_age']],
                ['Trong độ tuổi cần theo dõi tuyển quân', $data['nvqs']['tracking_age']],
                ['Đã đăng ký NVQS', $data['nvqs']['registered']],
                ['Chưa đăng ký NVQS', $data['nvqs']['unregistered']],
                ['Đã sơ tuyển', $data['nvqs']['preliminary_done']],
                ['Đã khám tuyển', $data['nvqs']['medical_done']],
                ['Tổng dân quân', $data['militia']['total']],
                ['Tổng lực lượng ANTT cơ sở', $data['security_force']['total']],
            ], $filters);
        }
        if (str_starts_with($mode, 'security_records') || str_starts_with($mode, 'security_record')) {
            $rows = $this->paginateSecurityRecords($filters)['items'];
            return $this->table('Danh sách theo dõi ANTT', ['Mã NK','Họ tên','Mã hộ','Loại hồ sơ','Phân loại','Trạng thái','Ngày phát sinh','Rà soát tiếp','Nội dung','Kết quả','Ghi chú'], array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['household_code'],$r['record_type_label'],$r['classification'],$r['current_status_label'],$r['occurred_date'],$r['next_review_date'],$r['summary'],$r['result'],$r['note']], $rows), $filters);
        }
        if (str_starts_with($mode, 'incidents') || str_starts_with($mode, 'incident')) {
            $rows = $this->paginateIncidents($filters)['items'];
            return $this->table('Danh sách vụ việc ANTT', ['Mã vụ việc','Ngày xảy ra','Giờ','Loại vụ việc','Địa điểm','Khu vực','Trạng thái','Người trình báo','Nội dung','Hướng xử lý','Kết quả'], array_map(fn($r) => [$r['incident_code'],$r['occurred_date'],$r['occurred_time'],$r['incident_type_label'],$r['location'],$r['area_code'],$r['incident_status_label'],$r['reporter_name'],$r['summary'],$r['handling_direction'],$r['result']], $rows), $filters);
        }
        if (str_starts_with($mode, 'militia')) return $this->forceReport('Dân quân tự vệ', $this->paginateMilitia($filters)['items'], 'militia', $filters);
        if (str_starts_with($mode, 'security_force') || str_starts_with($mode, 'antt')) return $this->forceReport('Lực lượng tham gia bảo vệ ANTT ở cơ sở', $this->paginateSecurityForce($filters)['items'], 'security', $filters);
        $filters['metric'] = match ($mode) {
            'upcoming_registration' => 'warning_age',
            'registration_age' => 'registration_age',
            'tracking_age' => 'tracking_age',
            'unregistered' => 'unregistered',
            'preliminary_missing' => 'preliminary_missing',
            'medical_missing' => 'medical_missing',
            'enlisted' => 'enlisted',
            'active_service' => 'active_service',
            default => $filters['metric'] ?? '',
        };
        $rows = $this->paginateNvqs($filters)['items'];
        return $this->table('Danh sách nghĩa vụ quân sự', ['Mã NK','Họ tên','Ngày sinh','Giới tính','Mã hộ','Năm','Đăng ký','Sơ tuyển','Khám tuyển','Điều kiện','Tuyển chọn','Đơn vị nhập ngũ','Ghi chú'], array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['date_of_birth'],$r['gender'],$r['household_code'],$r['recruitment_year'],$r['registered_status_label'],$r['preliminary_status_label'],$r['medical_status_label'] ?? $r['medical_exam_status_label'],$r['eligibility_status_label'],$r['selection_status_label'],$r['enlistment_unit'],$r['note']], $rows), $filters);
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
        $this->assertSchemaReady();
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
        $metric = (string) ($filters['metric'] ?? '');
        if ($metric === 'active') $where[] = "$alias.participation_status='ACTIVE'";
        if ($alias === 'm' && $metric === 'completed_or_ended') $where[] = "$alias.participation_status IN ('COMPLETED','ENDED')";
        if ($alias === 's' && $metric === 'leaders') $where[] = "$alias.position_code='LEADER'";
        if ($alias === 's' && $metric === 'deputies') $where[] = "$alias.position_code='DEPUTY'";
        if ($alias === 's' && $metric === 'members') $where[] = "$alias.position_code='MEMBER'";
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
        $this->assertSchemaReady();
        $row = $this->fetchOne('SELECT ' . $alias . '.*, ' . $this->citizenSelect() . ' ' . $this->personJoin($table, $alias) . ' WHERE ' . $alias . '.id=:id AND ' . $alias . '.status<>"DELETED" AND ' . $this->tenantWhere($alias, $table) . ' AND ' . $this->tenantWhere('c', 'citizens'), $this->withTenant(['id' => $id]));
        return $row ? $this->$normalizer($row) : null;
    }

    private function saveGeneric(string $table, array $params, ?int $id, string $finder): array
    {
        $this->assertSchemaReady();
        if ($id && !$this->$finder($id)) throw new RuntimeException('Không tìm thấy bản ghi Quốc phòng - An ninh');
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
        $this->assertSchemaReady();
        if (!$this->$finder($id)) throw new RuntimeException('Không tìm thấy bản ghi Quốc phòng - An ninh');
        $this->execute('UPDATE ' . $table . ' SET status="DELETED", deleted_at=NOW(), deleted_by=:user, updated_by=:user WHERE id=:id AND ' . $this->tenantWhere($table), $this->withTenant(['id' => $id, 'user' => $userId]));
    }

    private function nvqsParams(array $data, int $userId): array
    {
        $citizenId = $this->requireCitizen($data);
        $year = (int) ($data['recruitment_year'] ?? $data['year'] ?? date('Y'));
        if ($year < 1900 || $year > 2200) throw new RuntimeException('Năm tuyển quân không hợp lệ');
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
        if ($team === '') throw new RuntimeException('Tổ ANTT là bắt buộc');
        return ['citizen_id'=>$this->requireCitizen($data),'team_name'=>$team,'position_code'=>$this->enum($data['position_code'] ?? 'MEMBER', self::SECURITY_POSITIONS, 'MEMBER'),'joined_date'=>$this->dateValue($data['joined_date'] ?? ''),'ended_date'=>$this->dateValue($data['ended_date'] ?? ''),'area_in_charge'=>$this->nullable($data['area_in_charge'] ?? ''),'participation_status'=>$this->enum($data['participation_status'] ?? 'ACTIVE', self::SECURITY_STATUS, 'ACTIVE'),'reason'=>$this->nullable($data['reason'] ?? ''),'note'=>$this->nullable($data['note'] ?? ''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function requireCitizen(array $data): int
    {
        $id = (int) ($data['citizen_id'] ?? $data['person_id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Vui lòng chọn nhân khẩu từ danh sách.');
        if (!$this->citizenExists($id)) throw new RuntimeException('Không tìm thấy nhân khẩu trong tenant hiện tại');
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

    private function securityRecordWhere(array $filters): array
    {
        $where = ['r.status <> "DELETED"', $this->tenantWhere('r', 'defense_security_records'), $this->tenantWhere('c', 'citizens')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(c.full_name LIKE :search OR c.citizen_code LIKE :search OR h.household_code LIKE :search OR h.address LIKE :search OR r.classification LIKE :search OR r.summary LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        $type = strtoupper(trim((string) ($filters['record_type'] ?? $filters['type'] ?? '')));
        if ($type !== '' && isset(self::SECURITY_RECORD_TYPES[$type])) { $where[] = 'r.record_type=:record_type'; $params['record_type'] = $type; }
        $status = strtoupper(trim((string) ($filters['current_status'] ?? $filters['status'] ?? '')));
        if ($status !== '' && isset(self::SECURITY_RECORD_STATUS[$status])) { $where[] = 'r.current_status=:current_status'; $params['current_status'] = $status; }
        $metric = (string) ($filters['metric'] ?? '');
        if ($metric === 'review_due') $where[] = 'r.next_review_date IS NOT NULL AND r.next_review_date >= CURDATE() AND r.next_review_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND r.current_status NOT IN ("COMPLETED","CLOSED")';
        if ($metric === 'review_overdue') $where[] = 'r.next_review_date IS NOT NULL AND r.next_review_date < CURDATE() AND r.current_status NOT IN ("COMPLETED","CLOSED")';
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['full_name'=>'c.full_name','occurred_date'=>'r.occurred_date','next_review_date'=>'r.next_review_date','updated_at'=>'COALESCE(r.updated_at,r.created_at)'], 'updated_at', 'DESC', ['c.full_name ASC'])];
    }

    private function incidentWhere(array $filters): array
    {
        $where = ['i.status <> "DELETED"', $this->tenantWhere('i', 'defense_security_incidents')];
        $params = $this->withTenant();
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(i.incident_code LIKE :search OR i.location LIKE :search OR i.area_code LIKE :search OR i.summary LIKE :search OR i.reporter_name LIKE :search)'; $params['search'] = '%' . $search . '%'; }
        $type = trim((string) ($filters['incident_type_code'] ?? $filters['type'] ?? ''));
        if ($type !== '') { $where[] = 'i.incident_type_code=:incident_type_code'; $params['incident_type_code'] = $type; }
        $status = strtoupper(trim((string) ($filters['incident_status'] ?? $filters['status'] ?? '')));
        if ($status !== '' && isset(self::INCIDENT_STATUS[$status])) { $where[] = 'i.incident_status=:incident_status'; $params['incident_status'] = $status; }
        $from = $this->dateValue($filters['from_date'] ?? '');
        $to = $this->dateValue($filters['to_date'] ?? '');
        if ($from) { $where[] = 'i.occurred_date>=:from_date'; $params['from_date'] = $from; }
        if ($to) { $where[] = 'i.occurred_date<=:to_date'; $params['to_date'] = $to; }
        $year = $this->year($filters);
        if (!$from && !$to && $year > 0) { $where[] = 'YEAR(i.occurred_date)=:year'; $params['year'] = $year; }
        $metric = (string) ($filters['metric'] ?? '');
        if ($metric === 'month') $where[] = 'YEAR(i.occurred_date)=YEAR(CURDATE()) AND MONTH(i.occurred_date)=MONTH(CURDATE())';
        if ($metric === 'processing') $where[] = 'i.incident_status IN ("VERIFYING","PROCESSING","COORDINATING")';
        if ($metric === 'transferred') $where[] = 'i.incident_status="TRANSFERRED"';
        if ($metric === 'resolved') $where[] = 'i.incident_status IN ("RESOLVED","CLOSED")';
        return ['WHERE ' . implode(' AND ', $where), $params, $this->listOrder($filters, ['incident_code'=>'i.incident_code','occurred_date'=>'i.occurred_date','updated_at'=>'COALESCE(i.updated_at,i.created_at)'], 'occurred_date', 'DESC', ['i.id DESC'])];
    }

    private function securityRecordParams(array $data, int $userId): array
    {
        return ['citizen_id'=>$this->requireCitizen($data),'record_type'=>$this->enum($data['record_type'] ?? 'WATCHLIST', self::SECURITY_RECORD_TYPES, 'WATCHLIST'),'classification'=>$this->nullable($data['classification'] ?? ''),'summary'=>$this->nullable($data['summary'] ?? $data['reason'] ?? ''),'legal_basis'=>$this->nullable($data['legal_basis'] ?? ''),'decision_number'=>$this->nullable($data['decision_number'] ?? ''),'issuing_authority'=>$this->nullable($data['issuing_authority'] ?? ''),'occurred_date'=>$this->dateValue($data['occurred_date'] ?? ''),'start_date'=>$this->dateValue($data['start_date'] ?? ''),'completion_date'=>$this->dateValue($data['completion_date'] ?? ''),'source'=>$this->nullable($data['source'] ?? ''),'measures'=>$this->nullable($data['measures'] ?? ''),'result'=>$this->nullable($data['result'] ?? ''),'current_status'=>$this->enum($data['current_status'] ?? 'ACTIVE', self::SECURITY_RECORD_STATUS, 'ACTIVE'),'next_review_date'=>$this->dateValue($data['next_review_date'] ?? ''),'note'=>$this->nullable($data['note'] ?? ''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function incidentParams(array $data, int $userId, ?int $id): array
    {
        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') throw new RuntimeException('Nội dung vụ việc là bắt buộc');
        $occurred = $this->dateValue($data['occurred_date'] ?? '');
        if (!$occurred) throw new RuntimeException('Ngày xảy ra vụ việc là bắt buộc');
        return ['incident_code'=>$this->incidentCode($data, $id),'incident_type_code'=>$this->incidentTypeCode($data['incident_type_code'] ?? $data['type'] ?? 'OTHER'),'occurred_date'=>$occurred,'occurred_time'=>$this->timeValue($data['occurred_time'] ?? ''),'location'=>$this->nullable($data['location'] ?? ''),'area_code'=>$this->nullable($data['area_code'] ?? ''),'summary'=>$summary,'asset_description'=>$this->nullable($data['asset_description'] ?? ''),'estimated_damage'=>$this->decimalValue($data['estimated_damage'] ?? null),'reporter_name'=>$this->nullable($data['reporter_name'] ?? ''),'receiving_agency'=>$this->nullable($data['receiving_agency'] ?? ''),'handling_direction'=>$this->nullable($data['handling_direction'] ?? ''),'result'=>$this->nullable($data['result'] ?? ''),'incident_status'=>$this->enum($data['incident_status'] ?? 'NEW', self::INCIDENT_STATUS, 'NEW'),'note'=>$this->nullable($data['note'] ?? ''),'created_by'=>$userId,'updated_by'=>$userId];
    }

    private function normalizeSecurityRecord(array $row): array
    {
        return array_merge($this->normalizeCitizen($row), ['id'=>(int)$row['id'],'citizen_id'=>(int)$row['citizen_id'],'record_type'=>(string)$row['record_type'],'record_type_label'=>self::SECURITY_RECORD_TYPES[$row['record_type']]??'Trường hợp cần chú ý về ANTT','classification'=>(string)($row['classification']??''),'summary'=>(string)($row['summary']??''),'legal_basis'=>(string)($row['legal_basis']??''),'decision_number'=>(string)($row['decision_number']??''),'issuing_authority'=>(string)($row['issuing_authority']??''),'occurred_date'=>$row['occurred_date']??null,'start_date'=>$row['start_date']??null,'completion_date'=>$row['completion_date']??null,'source'=>(string)($row['source']??''),'measures'=>(string)($row['measures']??''),'result'=>(string)($row['result']??''),'current_status'=>(string)$row['current_status'],'current_status_label'=>self::SECURITY_RECORD_STATUS[$row['current_status']]??'Đang theo dõi','next_review_date'=>$row['next_review_date']??null,'note'=>(string)($row['note']??'')]);
    }

    private function normalizeIncident(array $row): array
    {
        return ['id'=>(int)$row['id'],'incident_code'=>(string)$row['incident_code'],'incident_type_code'=>(string)$row['incident_type_code'],'incident_type_label'=>(string)($row['incident_type_label']??$row['incident_type_code']),'occurred_date'=>$row['occurred_date']??null,'occurred_time'=>$row['occurred_time']??null,'location'=>(string)($row['location']??''),'area_code'=>(string)($row['area_code']??''),'summary'=>(string)($row['summary']??''),'asset_description'=>(string)($row['asset_description']??''),'estimated_damage'=>$row['estimated_damage']??null,'reporter_name'=>(string)($row['reporter_name']??''),'receiving_agency'=>(string)($row['receiving_agency']??''),'handling_direction'=>(string)($row['handling_direction']??''),'result'=>(string)($row['result']??''),'incident_status'=>(string)$row['incident_status'],'incident_status_label'=>self::INCIDENT_STATUS[$row['incident_status']]??'Mới tiếp nhận','note'=>(string)($row['note']??'')];
    }

    private function securityRecordDashboard(): array
    {
        return ['total'=>$this->countTable('defense_security_records'),'criminal_records'=>$this->countTable('defense_security_records', "record_type='CRIMINAL_RECORD'"),'administrative_records'=>$this->countTable('defense_security_records', "record_type='ADMINISTRATIVE_RECORD'"),'watchlist'=>$this->countTable('defense_security_records', "record_type='WATCHLIST'"),'review_due'=>$this->countTable('defense_security_records', "next_review_date IS NOT NULL AND next_review_date>=CURDATE() AND next_review_date<=DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND current_status NOT IN ('COMPLETED','CLOSED')"),'review_overdue'=>$this->countTable('defense_security_records', "next_review_date IS NOT NULL AND next_review_date<CURDATE() AND current_status NOT IN ('COMPLETED','CLOSED')")];
    }

    private function incidentDashboard(array $filters): array
    {
        $month = $this->paginateIncidents(['metric'=>'month','page'=>1,'pageSize'=>1]);
        return ['month'=>(int)$month['total'],'theft'=>$this->countTable('defense_security_incidents', "incident_type_code IN ('THEFT','PROPERTY_LOSS')"),'fight'=>$this->countTable('defense_security_incidents', "incident_type_code IN ('FIGHT','DISTURBANCE')"),'conflict'=>$this->countTable('defense_security_incidents', "incident_type_code IN ('CONFLICT','FAMILY_CONFLICT')"),'processing'=>$this->paginateIncidents(['metric'=>'processing','page'=>1,'pageSize'=>1])['total'] ?? 0,'transferred'=>$this->paginateIncidents(['metric'=>'transferred','page'=>1,'pageSize'=>1])['total'] ?? 0,'resolved'=>$this->paginateIncidents(['metric'=>'resolved','page'=>1,'pageSize'=>1])['total'] ?? 0];
    }

    private function recordsByCitizen(int $citizenId): array { return array_map(fn($r) => $this->normalizeSecurityRecord($r), $this->fetchAll('SELECT r.*, ' . $this->citizenSelect() . ' ' . $this->personJoin('defense_security_records', 'r') . ' WHERE r.citizen_id=:citizen_id AND r.status<>"DELETED" AND ' . $this->tenantWhere('r', 'defense_security_records') . ' ORDER BY COALESCE(r.next_review_date,r.updated_at,r.created_at) DESC', $this->withTenant(['citizen_id'=>$citizenId]))); }

    private function incidentsByCitizen(int $citizenId): array { return array_map(fn($r) => $this->normalizeIncident($r), $this->fetchAll('SELECT i.*, t.name AS incident_type_label FROM defense_security_incident_people p INNER JOIN defense_security_incidents i ON i.id=p.incident_id LEFT JOIN defense_security_incident_types t ON t.code=i.incident_type_code AND t.village_id=i.village_id WHERE p.citizen_id=:citizen_id AND p.status<>"DELETED" AND i.status<>"DELETED" AND ' . $this->tenantWhere('p', 'defense_security_incident_people') . ' AND ' . $this->tenantWhere('i', 'defense_security_incidents') . ' ORDER BY i.occurred_date DESC', $this->withTenant(['citizen_id'=>$citizenId]))); }

    private function securityRecordLogs(int $recordId): array { return $this->fetchAll('SELECT id, event_date, event_type, content, result, next_review_date, created_at FROM defense_security_record_logs WHERE record_id=:record_id AND status<>"DELETED" AND ' . $this->tenantWhere('defense_security_record_logs') . ' ORDER BY event_date DESC, id DESC', $this->withTenant(['record_id'=>$recordId])); }
    private function securityRecordLog(int $id): ?array { return $this->fetchOne('SELECT id, record_id, event_date, event_type, content, result, next_review_date, created_at FROM defense_security_record_logs WHERE id=:id AND status<>"DELETED" AND ' . $this->tenantWhere('defense_security_record_logs'), $this->withTenant(['id'=>$id])); }
    private function incidentLogs(int $incidentId): array { return $this->fetchAll('SELECT id, event_at, event_type, content, new_status, created_at FROM defense_security_incident_logs WHERE incident_id=:incident_id AND status<>"DELETED" AND ' . $this->tenantWhere('defense_security_incident_logs') . ' ORDER BY event_at DESC, id DESC', $this->withTenant(['incident_id'=>$incidentId])); }
    private function incidentLog(int $id): ?array { return $this->fetchOne('SELECT id, incident_id, event_at, event_type, content, new_status, created_at FROM defense_security_incident_logs WHERE id=:id AND status<>"DELETED" AND ' . $this->tenantWhere('defense_security_incident_logs'), $this->withTenant(['id'=>$id])); }
    private function incidentPeople(int $incidentId): array { return $this->fetchAll('SELECT p.*, c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.address FROM defense_security_incident_people p LEFT JOIN citizens c ON c.id=p.citizen_id LEFT JOIN households h ON h.id=c.household_id WHERE p.incident_id=:incident_id AND p.status<>"DELETED" AND ' . $this->tenantWhere('p', 'defense_security_incident_people') . ' AND (c.id IS NULL OR ' . $this->tenantWhere('c', 'citizens') . ') ORDER BY p.id ASC', $this->withTenant(['incident_id'=>$incidentId])); }
    private function incidentPerson(int $id): ?array { return $this->fetchOne('SELECT p.*, c.citizen_code, c.full_name, c.date_of_birth, c.gender, h.household_code, h.address FROM defense_security_incident_people p LEFT JOIN citizens c ON c.id=p.citizen_id LEFT JOIN households h ON h.id=c.household_id WHERE p.id=:id AND p.status<>"DELETED" AND ' . $this->tenantWhere('p', 'defense_security_incident_people') . ' AND (c.id IS NULL OR ' . $this->tenantWhere('c', 'citizens') . ')', $this->withTenant(['id'=>$id])); }

    private function incidentTypePairs(): array { return array_map(fn($row) => ['value'=>$row['code'], 'label'=>$row['name']], $this->fetchAll('SELECT code, name FROM defense_security_incident_types WHERE is_active=1 AND ' . $this->tenantWhere('defense_security_incident_types') . ' ORDER BY display_order ASC, name ASC', $this->withTenant())); }

    private function ensureDefaultIncidentTypes(): void
    {
        $this->assertIncidentTypesReady();
    }

    private function incidentCode(array $data, ?int $id): string
    {
        $code = trim((string) ($data['incident_code'] ?? ''));
        if ($code !== '') return $code;
        if ($id) { $existing = $this->findIncident($id); if ($existing && $existing['incident_code']) return $existing['incident_code']; }
        return 'ANTT-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function incidentTypeCode(mixed $value): string { $value = strtoupper(trim((string) $value)); return $value !== '' ? preg_replace('/[^A-Z0-9_]/', '', $value) ?: 'OTHER' : 'OTHER'; }
    private function timeValue(mixed $value): ?string { $value = trim((string) $value); if ($value === '') return null; if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) return strlen($value) === 5 ? $value . ':00' : $value; throw new RuntimeException('Giờ phải theo định dạng HH:mm'); }
    private function dateTimeValue(mixed $value): string { $value = trim((string) $value); if ($value === '') return date('Y-m-d H:i:s'); if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $value)) return str_replace('T', ' ', strlen($value) === 16 ? $value . ':00' : $value); $date = $this->dateValue($value); if ($date) return $date . ' 00:00:00'; throw new RuntimeException('Thời gian không hợp lệ'); }
    private function decimalValue(mixed $value): ?string { if ($value === null || trim((string)$value) === '') return null; $value = str_replace(',', '', trim((string)$value)); if (!is_numeric($value)) throw new RuntimeException('Giá trị thiệt hại không hợp lệ'); return (string) $value; }
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
            'warning_age' => 'Nam công dân đủ 16 tuổi trong năm ' . $year . ', dự kiến đến tuổi đăng ký NVQS trong năm ' . ($year + 1) . '.',
            'registration_age' => 'Nam công dân đủ 17 tuổi trong năm ' . $year . ', thuộc diện lập danh sách đăng ký NVQS.',
            'tracking_age' => 'Nam công dân từ đủ 18 tuổi trong độ tuổi cần theo dõi tuyển quân.',
            'unregistered' => 'Thuộc diện đăng ký/theo dõi NVQS nhưng chưa có thông tin đã đăng ký.',
            'preliminary_missing' => 'Thuộc diện theo dõi tuyển quân nhưng chưa cập nhật sơ tuyển.',
            'medical_missing' => 'Thuộc diện theo dõi tuyển quân nhưng chưa cập nhật khám tuyển.',
            'registered' => 'Đã có thông tin đăng ký NVQS trong năm tuyển quân.',
            'preliminary_done' => 'Đã có thông tin sơ tuyển NVQS.',
            'medical_done' => 'Đã có thông tin khám tuyển NVQS.',
            'eligible' => 'Hồ sơ nghiệp vụ đang ghi nhận đủ điều kiện.',
            'deferred' => 'Hồ sơ nghiệp vụ đang ghi nhận tạm hoãn.',
            'exempt' => 'Hồ sơ nghiệp vụ đang ghi nhận miễn.',
            'selected' => 'Hồ sơ nghiệp vụ đang ghi nhận trúng tuyển.',
            'enlisted' => 'Hồ sơ nghiệp vụ đang ghi nhận đã nhập ngũ.',
            'active_service' => 'Hồ sơ nghiệp vụ đang ghi nhận đang tại ngũ.',
            'discharged' => 'Hồ sơ nghiệp vụ có ngày xuất ngũ.',
            'active' => 'Hồ sơ nghiệp vụ đang ở trạng thái hoạt động/tham gia.',
            'completed_or_ended' => 'Hồ sơ dân quân đã hoàn thành hoặc thôi tham gia.',
            'leaders' => 'Hồ sơ ANTT có chức vụ Tổ trưởng.',
            'deputies' => 'Hồ sơ ANTT có chức vụ Tổ phó.',
            'members' => 'Hồ sơ ANTT có chức vụ Tổ viên.',
            'total' => 'Có hồ sơ nghiệp vụ liên kết với nhân khẩu.',
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
        $settings = self::SETTINGS;
        $year = $year ?: (int) date('Y');
        foreach (array_keys($settings) as $key) {
            $row = $this->fetchOne(
                'SELECT setting_value FROM defense_security_settings WHERE ' . $this->tenantWhere('defense_security_settings') . ' AND setting_key=:setting_key AND (applied_year IS NULL OR applied_year=:year) ORDER BY CASE WHEN applied_year=:year THEN 0 ELSE 1 END, id DESC LIMIT 1',
                $this->withTenant(['setting_key' => $key, 'year' => $year])
            );
            if ($row) $settings[$key] = (int) $row['setting_value'];
        }
        return $settings;
    }

    private function ensureDefaultSettings(): void
    {
        $this->assertDefaultSettingsReady();
    }

    private function normalizeNvqs(array $row): array
    {
        $base = $this->normalizeCitizen($row);
        return array_merge($base, ['id'=>(int)($row['id'] ?? 0),'citizen_id'=>(int)$row['citizen_id'],'recruitment_year'=>(int)$row['recruitment_year'],'registered_status'=>(string)$row['registered_status'],'registered_status_label'=>self::YES_NO[$row['registered_status']]??'Không','registration_date'=>$row['registration_date']??null,'preliminary_status'=>(string)$row['preliminary_status'],'preliminary_status_label'=>self::PRELIMINARY[$row['preliminary_status']]??'Chưa cập nhật','preliminary_date'=>$row['preliminary_date']??null,'medical_exam_status'=>(string)$row['medical_exam_status'],'medical_exam_status_label'=>self::MEDICAL[$row['medical_exam_status']]??'Chưa cập nhật','medical_exam_date'=>$row['medical_exam_date']??null,'health_classification'=>(string)($row['health_classification']??''),'eligibility_status'=>(string)$row['eligibility_status'],'eligibility_status_label'=>self::ELIGIBILITY[$row['eligibility_status']]??'Chưa xác định','deferment_reason'=>(string)($row['deferment_reason']??''),'exemption_reason'=>(string)($row['exemption_reason']??''),'selection_status'=>(string)$row['selection_status'],'selection_status_label'=>self::SELECTION[$row['selection_status']]??'Chưa trúng tuyển','order_received'=>(bool)$row['order_received'],'enlistment_date'=>$row['enlistment_date']??null,'enlistment_unit'=>(string)($row['enlistment_unit']??''),'active_service'=>(bool)$row['active_service'],'discharge_date'=>$row['discharge_date']??null,'discharge_unit'=>(string)($row['discharge_unit']??''),'completed_service'=>(bool)$row['completed_service'],'note'=>(string)($row['note']??'')]);
    }

    private function normalizeMilitia(array $row): array
    {
        return array_merge($this->normalizeCitizen($row), ['id'=>(int)$row['id'],'citizen_id'=>(int)$row['citizen_id'],'militia_type'=>(string)$row['militia_type'],'militia_type_label'=>self::MILITIA_TYPES[$row['militia_type']]??'Khác','position_name'=>(string)($row['position_name']??''),'unit_name'=>(string)($row['unit_name']??''),'joined_date'=>$row['joined_date']??null,'ended_date'=>$row['ended_date']??null,'training_name'=>(string)($row['training_name']??''),'training_date'=>$row['training_date']??null,'training_result'=>(string)($row['training_result']??''),'participation_status'=>(string)$row['participation_status'],'participation_status_label'=>self::PARTICIPATION[$row['participation_status']]??'Đang tham gia','reason'=>(string)($row['reason']??''),'note'=>(string)($row['note']??'')]);
    }

    private function normalizeSecurityForce(array $row): array
    {
        return array_merge($this->normalizeCitizen($row), ['id'=>(int)$row['id'],'citizen_id'=>(int)$row['citizen_id'],'team_name'=>(string)$row['team_name'],'position_code'=>(string)$row['position_code'],'position_label'=>self::SECURITY_POSITIONS[$row['position_code']]??'Tổ viên','joined_date'=>$row['joined_date']??null,'ended_date'=>$row['ended_date']??null,'area_in_charge'=>(string)($row['area_in_charge']??''),'participation_status'=>(string)$row['participation_status'],'participation_status_label'=>self::SECURITY_STATUS[$row['participation_status']]??'Đang hoạt động','reason'=>(string)($row['reason']??''),'note'=>(string)($row['note']??'')]);
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
        if ($age === (int) $settings['nvqs_warning_age']) $warnings[] = 'Sắp đến tuổi đăng ký NVQS';
        if ($age === (int) $settings['nvqs_registration_age']) $warnings[] = 'Đến tuổi đăng ký NVQS';
        if ($age >= (int) $settings['nvqs_call_age'] && $age <= (int) $settings['nvqs_follow_end_age']) $warnings[] = 'Trong độ tuổi cần theo dõi tuyển quân';
        if ($warnings && !$this->existingNvqs((int) $citizen['id'], $year)) $warnings[] = 'Chưa có hồ sơ NVQS';
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
        throw new RuntimeException('Ngày phải theo định dạng dd/mm/yyyy');
    }

    private function pairs(array $map): array
    {
        return array_map(fn($key, $label) => ['value' => $key, 'label' => $label], array_keys($map), $map);
    }

    private function table(string $title, array $headers, array $rows, array $filters): array
    {
        return ['title'=>$title,'headers'=>$headers,'rows'=>$rows,'totalRows'=>count($rows),'filters'=>$filters,'generatedAt'=>date('c'),'meta'=>['unit'=>'Thôn','report_year'=>$this->year($filters)]];
    }

    private function forceReport(string $title, array $rows, string $type, array $filters): array
    {
        $headers = $type === 'security' ? ['Mã NK','Họ tên','Mã hộ','Tổ ANTT','Chức vụ','Ngày tham gia','Ngày kết thúc','Khu vực phụ trách','Trạng thái','Ghi chú'] : ['Mã NK','Họ tên','Mã hộ','Loại dân quân','Chức vụ','Đơn vị/tổ','Ngày tham gia','Huấn luyện','Kết quả','Trạng thái','Ghi chú'];
        $body = $type === 'security' ? array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['household_code'],$r['team_name'],$r['position_label'],$r['joined_date'],$r['ended_date'],$r['area_in_charge'],$r['participation_status_label'],$r['note']], $rows) : array_map(fn($r) => [$r['citizen_code'],$r['full_name'],$r['household_code'],$r['militia_type_label'],$r['position_name'],$r['unit_name'],$r['joined_date'],$r['training_name'],$r['training_result'],$r['participation_status_label'],$r['note']], $rows);
        return $this->table($title, $headers, $body, $filters);
    }
}






