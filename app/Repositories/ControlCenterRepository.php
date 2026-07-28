<?php

namespace App\Repositories;

use App\Config\CitizenPolicyDefaults;
use App\Core\Database;
use PDO;

final class ControlCenterRepository
{
    private ?PDO $db = null;
    private array $tableCache = [];

    public function dashboardMetrics(): array
    {
        $citizenWhere = $this->activeCitizenWhere();
        $totalCitizens = $this->count('citizens', $citizenWhere);
        $insuredCitizens = $this->count('citizens', $citizenWhere . ' AND has_health_insurance = 1');

        return [
            'totalUnits' => $this->count('villages', "status = 'ACTIVE'"),
            'totalHouseholds' => $this->count('households', "status <> 'DELETED'"),
            'totalCitizens' => $totalCitizens,
            'totalChildren' => $this->count('citizens', $citizenWhere . ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 16'),
            'totalElderly' => $this->count('citizens', $citizenWhere . ' AND TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ' . CitizenPolicyDefaults::ELDERLY_OCCUPATION_DEFAULT_AGE),
            'totalWorkers' => $this->count('citizens', $citizenWhere . " AND (employed = 1 OR freelance_labor = 1 OR out_province_labor = 1 OR foreign_labor = 1)"),
            'totalPartyMembers' => $this->partyMemberCount(),
            'healthInsuranceRate' => $totalCitizens > 0 ? round(($insuredCitizens / $totalCitizens) * 100, 1) : 0.0,
        ];
    }

    public function units(): array
    {
        if (!$this->tableExists('villages')) {
            return [];
        }

        $sql = "
            SELECT
                v.id,
                v.code,
                v.name,
                v.domain,
                v.subdomain,
                v.logo_url,
                v.status,
                v.updated_at,
                COUNT(DISTINCT h.id) AS household_count,
                COUNT(DISTINCT c.id) AS citizen_count,
                MAX(CASE WHEN u.role IN ('SUPER_ADMIN','ADMIN') AND u.status = 'ACTIVE' THEN u.display_name ELSE NULL END) AS manager_name
            FROM villages v
            LEFT JOIN households h ON h.village_id = v.id AND h.status <> 'DELETED'
            LEFT JOIN citizens c ON c.village_id = v.id AND c.status <> 'DELETED' AND c.life_status = 'ALIVE'
            LEFT JOIN users u ON u.village_id = v.id AND u.status = 'ACTIVE'
            GROUP BY v.id, v.code, v.name, v.domain, v.subdomain, v.logo_url, v.status, v.updated_at
            ORDER BY v.status DESC, v.code ASC, v.name ASC
            LIMIT 200
        ";

        $items = $this->db()->query($sql)->fetchAll();
        return array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'domain' => (string) ($row['domain'] ?: $row['subdomain'] ?: ''),
            'logo' => (string) ($row['logo_url'] ?? ''),
            'status' => (string) $row['status'],
            'manager' => (string) ($row['manager_name'] ?: 'Chua gan'),
            'version' => defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1',
            'healthStatus' => $row['status'] === 'ACTIVE' ? 'OK' : 'LOCKED',
            'households' => (int) $row['household_count'],
            'citizens' => (int) $row['citizen_count'],
            'updatedAt' => $row['updated_at'] ?? null,
        ], $items ?: []);
    }

    public function accountRoleSummary(): array
    {
        $counts = $this->roleCounts();
        $roles = [
            ['code' => 'SYSTEM_ADMIN', 'name' => 'System Admin', 'sourceRoles' => ['SUPER_ADMIN']],
            ['code' => 'COMMUNE_ADMIN', 'name' => 'Commune Admin', 'sourceRoles' => []],
            ['code' => 'VILLAGE_ADMIN', 'name' => 'Village Admin', 'sourceRoles' => ['ADMIN']],
            ['code' => 'STAFF', 'name' => 'Staff', 'sourceRoles' => ['OFFICER']],
            ['code' => 'VIEWER', 'name' => 'Viewer', 'sourceRoles' => ['VIEWER']],
        ];

        return array_map(static function (array $role) use ($counts): array {
            $total = 0;
            foreach ($role['sourceRoles'] as $sourceRole) {
                $total += (int) ($counts[$sourceRole] ?? 0);
            }

            return [
                'code' => $role['code'],
                'name' => $role['name'],
                'users' => $total,
                'status' => 'ACTIVE',
            ];
        }, $roles);
    }

    public function databaseHealth(): array
    {
        return Database::diagnostics();
    }

    private function partyMemberCount(): int
    {
        if ($this->tableExists('party_members')) {
            return $this->count('party_members', "status <> 'DELETED'");
        }

        return $this->count('citizens', $this->activeCitizenWhere() . ' AND party_member = 1');
    }

    private function roleCounts(): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $rows = $this->db()->query("SELECT role, COUNT(*) AS total FROM users WHERE status <> 'DELETED' GROUP BY role")->fetchAll();
        $counts = [];
        foreach ($rows ?: [] as $row) {
            $counts[(string) $row['role']] = (int) $row['total'];
        }
        return $counts;
    }

    private function count(string $table, string $where = '1=1'): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS total FROM `' . str_replace('`', '', $table) . '` WHERE ' . $where;
        $row = $this->db()->query($sql)->fetch();
        return (int) ($row['total'] ?? 0);
    }

    private function activeCitizenWhere(): string
    {
        return "status <> 'DELETED' AND life_status = 'ALIVE'";
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        $row = $stmt->fetch();
        return $this->tableCache[$table] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
