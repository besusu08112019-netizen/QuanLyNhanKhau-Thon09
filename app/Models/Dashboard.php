<?php

namespace App\Models;

use App\Core\BaseModel;

final class Dashboard extends BaseModel
{
    public function summary(array $filters = []): array
    {
        return [
            'metrics' => $this->metrics($filters),
            'charts' => [
                'population' => $this->populationChart($filters),
                'households' => $this->householdChart($filters),
                'ages' => $this->ageChart($filters),
                'residency' => $this->residencyChart($filters),
                'hamlets' => $this->hamletChart($filters),
                'monthlyChanges' => $this->monthlyChangeChart($filters),
                'poverty' => $this->povertyChart($filters),
            ],
            'filters' => $this->normalizeFilters($filters),
            'generatedAt' => date('c'),
        ];
    }

    public function metrics(array $filters = []): array
    {
        [$householdWhere, $householdParams] = $this->householdWhere($filters);
        [$citizenWhere, $citizenParams] = $this->citizenWhere($filters);

        $households = $this->fetchOne("SELECT COUNT(*) AS total_households, SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END) AS poor_households, SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END) AS near_poor_households FROM households h $householdWhere", $householdParams) ?: [];
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total_citizens, SUM(CASE WHEN c.gender='Nam' THEN 1 ELSE 0 END) AS male_count, SUM(CASE WHEN c.gender='Nữ' THEN 1 ELSE 0 END) AS female_count, SUM(CASE WHEN c.life_status='ALIVE' THEN 1 ELSE 0 END) AS active_citizens, SUM(CASE WHEN c.residency_status='TEMPORARY' THEN 1 ELSE 0 END) AS temporary_count, SUM(CASE WHEN c.presence_status='AWAY' THEN 1 ELSE 0 END) AS away_count, SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) < 16 THEN 1 ELSE 0 END) AS children_count, SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) >= 60 THEN 1 ELSE 0 END) AS elderly_count, SUM(CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) BETWEEN 16 AND 59 THEN 1 ELSE 0 END) AS working_age_count FROM citizens c INNER JOIN households h ON h.id = c.household_id $citizenWhere", $citizenParams) ?: [];

        return [
            'total_households' => (int) ($households['total_households'] ?? 0),
            'total_citizens' => (int) ($citizens['total_citizens'] ?? 0),
            'male_count' => (int) ($citizens['male_count'] ?? 0),
            'female_count' => (int) ($citizens['female_count'] ?? 0),
            'active_citizens' => (int) ($citizens['active_citizens'] ?? 0),
            'children_count' => (int) ($citizens['children_count'] ?? 0),
            'elderly_count' => (int) ($citizens['elderly_count'] ?? 0),
            'working_age_count' => (int) ($citizens['working_age_count'] ?? 0),
            'temporary_count' => (int) ($citizens['temporary_count'] ?? 0),
            'away_count' => (int) ($citizens['away_count'] ?? 0),
            'poor_households' => (int) ($households['poor_households'] ?? 0),
            'near_poor_households' => (int) ($households['near_poor_households'] ?? 0),
        ];
    }

    public function populationChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(c.gender,''),'Khác') AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function householdChart(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        return $this->fetchAll("SELECT CASE h.status WHEN 'ACTIVE' THEN 'Đang hoạt động' WHEN 'INACTIVE' THEN 'Ngưng theo dõi' ELSE h.status END AS label, COUNT(*) AS value FROM households h $where GROUP BY h.status ORDER BY h.status", $params);
    }

    public function ageChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 5 THEN '0-5' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 15 THEN '6-15' WHEN TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()) <= 59 THEN '16-59' ELSE '60+' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY MIN(TIMESTAMPDIFF(YEAR,c.date_of_birth,CURDATE()))", $params);
    }

    public function residencyChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT CASE c.residency_status WHEN 'TEMPORARY' THEN 'Tạm trú' ELSE 'Thường trú' END AS label, COUNT(*) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY c.residency_status ORDER BY c.residency_status", $params);
    }

    public function hamletChart(array $filters = []): array
    {
        [$where, $params] = $this->citizenWhere($filters);
        return $this->fetchAll("SELECT COALESCE(NULLIF(h.area_code,''),'Thôn 09') AS label, COUNT(c.id) AS value FROM citizens c INNER JOIN households h ON h.id = c.household_id $where GROUP BY label ORDER BY label", $params);
    }

    public function monthlyChangeChart(array $filters = []): array
    {
        $rows = $this->fetchAll("SELECT DATE_FORMAT(effective_date, '%Y-%m') AS label, SUM(CASE WHEN type IN ('BIRTH','MOVE_IN','TEMPORARY_RESIDENCE') THEN 1 WHEN type IN ('DEATH','MOVE_OUT','TEMPORARY_ABSENCE') THEN -1 ELSE 0 END) AS value FROM movements WHERE status <> 'DELETED' AND effective_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY label ORDER BY label");
        return array_map(fn($row) => ['label' => $row['label'], 'value' => (int) $row['value']], $rows);
    }

    public function povertyChart(array $filters = []): array
    {
        [$where, $params] = $this->householdWhere($filters);
        $row = $this->fetchOne("SELECT SUM(CASE WHEN h.poor_household=1 THEN 1 ELSE 0 END) AS poor, SUM(CASE WHEN h.near_poor_household=1 THEN 1 ELSE 0 END) AS near_poor, SUM(CASE WHEN h.poor_household=0 AND h.near_poor_household=0 THEN 1 ELSE 0 END) AS normal FROM households h $where", $params) ?: [];
        return [
            ['label' => 'Hộ nghèo', 'value' => (int) ($row['poor'] ?? 0)],
            ['label' => 'Hộ cận nghèo', 'value' => (int) ($row['near_poor'] ?? 0)],
            ['label' => 'Hộ khác', 'value' => (int) ($row['normal'] ?? 0)],
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? '')) ?: null,
            'dateTo' => trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? '')) ?: null,
            'householdStatus' => trim((string) ($filters['householdStatus'] ?? $filters['household_status'] ?? '')) ?: null,
            'residencyStatus' => trim((string) ($filters['residencyStatus'] ?? $filters['residency_status'] ?? '')) ?: null,
            'presenceStatus' => trim((string) ($filters['presenceStatus'] ?? $filters['presence_status'] ?? '')) ?: null,
        ];
    }

    private function householdWhere(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $where = ['h.status <> "DELETED"'];
        $params = [];
        if ($filters['householdStatus']) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(h.created_at) >= :household_date_from'; $params['household_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(h.created_at) <= :household_date_to'; $params['household_date_to'] = $filters['dateTo']; }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function citizenWhere(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $where = ['c.status <> "DELETED"', 'h.status <> "DELETED"'];
        $params = [];
        if ($filters['householdStatus']) { $where[] = 'h.status = :household_status'; $params['household_status'] = $filters['householdStatus']; }
        if ($filters['residencyStatus']) { $where[] = 'c.residency_status = :residency_status'; $params['residency_status'] = $filters['residencyStatus']; }
        if ($filters['presenceStatus']) { $where[] = 'c.presence_status = :presence_status'; $params['presence_status'] = $filters['presenceStatus']; }
        if ($filters['dateFrom']) { $where[] = 'DATE(c.created_at) >= :citizen_date_from'; $params['citizen_date_from'] = $filters['dateFrom']; }
        if ($filters['dateTo']) { $where[] = 'DATE(c.created_at) <= :citizen_date_to'; $params['citizen_date_to'] = $filters['dateTo']; }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}
