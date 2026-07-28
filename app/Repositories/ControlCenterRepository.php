<?php

namespace App\Repositories;

use App\Config\CitizenPolicyDefaults;
use App\Core\Database;
use PDO;

final class ControlCenterRepository
{
    private ?PDO $db = null;
    private array $tableCache = [];
    private array $columnCache = [];

    public function dashboardMetrics(): array
    {
        $citizenWhere = $this->activeCitizenWhere();
        $totalCitizens = $this->count('citizens', $citizenWhere);
        $insuredCitizens = $this->count('citizens', $citizenWhere . ' AND has_health_insurance = 1');

        return [
            'totalUnits' => $this->count('villages', '1=1'),
            'activeUnits' => $this->count('villages', "status = 'ACTIVE'"),
            'inactiveUnits' => $this->count('villages', "status <> 'ACTIVE'"),
            'websiteOnlineUnits' => $this->tenantStatusCount('website_status', 'ONLINE'),
            'websiteOfflineUnits' => $this->tenantStatusCount('website_status', 'OFFLINE'),
            'databaseConnectedUnits' => $this->tenantStatusCount('database_status', 'CONNECTED'),
            'databaseDisconnectedUnits' => $this->tenantStatusCount('database_status', 'DISCONNECTED'),
            'lockedUnits' => $this->count('villages', "status <> 'ACTIVE'"),
            'latestBackupAt' => $this->latestTenantValue('last_backup_at'),
            'versions' => $this->tenantVersions(),
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

        $databaseName = $this->columnSql('villages', 'database_name', 'v.database_name', 'NULL AS database_name');
        $databaseHost = $this->columnSql('villages', 'database_host', 'v.database_host', 'NULL AS database_host');
        $databaseCharset = $this->columnSql('villages', 'database_charset', 'v.database_charset', 'NULL AS database_charset');
        $appVersion = $this->columnSql('villages', 'app_version', 'v.app_version', 'NULL AS app_version');
        $buildVersion = $this->columnSql('villages', 'build_version', 'v.build_version', 'NULL AS build_version');
        $schemaVersion = $this->columnSql('villages', 'schema_version', 'v.schema_version', 'NULL AS schema_version');
        $websiteStatus = $this->columnSql('villages', 'website_status', 'v.website_status', 'NULL AS website_status');
        $databaseStatus = $this->columnSql('villages', 'database_status', 'v.database_status', 'NULL AS database_status');
        $sslStatus = $this->columnSql('villages', 'ssl_status', 'v.ssl_status', 'NULL AS ssl_status');
        $lastCheckedAt = $this->columnSql('villages', 'last_checked_at', 'v.last_checked_at', 'NULL AS last_checked_at');
        $lastBackupAt = $this->columnSql('villages', 'last_backup_at', 'v.last_backup_at', 'NULL AS last_backup_at');
        $lastError = $this->columnSql('villages', 'last_error', 'v.last_error', 'NULL AS last_error');
        $managerName = $this->columnSql('villages', 'manager_name', 'v.manager_name AS registry_manager_name', 'NULL AS registry_manager_name');
        $sql = "
            SELECT
                v.id,
                v.code,
                v.name,
                v.domain,
                v.subdomain,
                v.logo_url,
                v.status,
                $databaseName,
                $databaseHost,
                $databaseCharset,
                $appVersion,
                $buildVersion,
                $schemaVersion,
                $websiteStatus,
                $databaseStatus,
                $sslStatus,
                $lastCheckedAt,
                $lastBackupAt,
                $lastError,
                $managerName,
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
            'databaseName' => (string) ($row['database_name'] ?? ''),
            'databaseHost' => (string) ($row['database_host'] ?? ''),
            'databaseCharset' => (string) ($row['database_charset'] ?: 'utf8mb4'),
            'logo' => (string) ($row['logo_url'] ?? ''),
            'status' => (string) $row['status'],
            'manager' => (string) ($row['registry_manager_name'] ?: $row['manager_name'] ?: 'Chua gan'),
            'version' => (string) ($row['app_version'] ?: (defined('APP_ASSET_VERSION') ? APP_ASSET_VERSION : '1')),
            'appVersion' => (string) ($row['app_version'] ?? ''),
            'buildVersion' => (string) ($row['build_version'] ?? ''),
            'schemaVersion' => (string) ($row['schema_version'] ?? ''),
            'healthStatus' => (string) ($row['database_status'] ?: ($row['status'] === 'ACTIVE' ? 'UNKNOWN' : 'LOCKED')),
            'websiteStatus' => (string) ($row['website_status'] ?: ($row['status'] === 'ACTIVE' ? 'UNKNOWN' : 'LOCKED')),
            'databaseStatus' => (string) ($row['database_status'] ?: ($row['status'] === 'ACTIVE' ? 'UNKNOWN' : 'LOCKED')),
            'sslStatus' => (string) ($row['ssl_status'] ?: 'UNKNOWN'),
            'lastCheckedAt' => $row['last_checked_at'] ?? null,
            'lastBackupAt' => $row['last_backup_at'] ?? null,
            'lastError' => (string) ($row['last_error'] ?? ''),
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

    private function tenantStatusCount(string $column, string $status): int
    {
        if (!$this->columnExists('villages', $column)) {
            return 0;
        }
        return $this->count('villages', $column . " = '" . str_replace("'", "''", $status) . "'");
    }

    private function latestTenantValue(string $column): ?string
    {
        if (!$this->tableExists('villages') || !$this->columnExists('villages', $column)) {
            return null;
        }
        $row = $this->db()->query('SELECT MAX(`' . str_replace('`', '', $column) . '`) AS value FROM villages')->fetch();
        return $row && $row['value'] ? (string) $row['value'] : null;
    }

    private function tenantVersions(): array
    {
        if (!$this->tableExists('villages') || !$this->columnExists('villages', 'app_version')) {
            return [];
        }
        $rows = $this->db()->query("SELECT COALESCE(NULLIF(app_version, ''), 'UNKNOWN') AS version, COUNT(*) AS total FROM villages GROUP BY COALESCE(NULLIF(app_version, ''), 'UNKNOWN') ORDER BY total DESC")->fetchAll();
        return array_map(static fn(array $row): array => [
            'version' => (string) $row['version'],
            'total' => (int) $row['total'],
        ], $rows ?: []);
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

    private function columnSql(string $table, string $column, string $presentSql, string $fallbackSql): string
    {
        return $this->columnExists($table, $column) ? $presentSql : $fallbackSql;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }
        $stmt = $this->db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        $row = $stmt->fetch();
        return $this->columnCache[$key] = ((int) ($row['total'] ?? 0) > 0);
    }

    private function db(): PDO
    {
        return $this->db ??= Database::pdo();
    }
}
