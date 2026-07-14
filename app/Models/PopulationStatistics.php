<?php

namespace App\Models;

use App\Core\BaseModel;

final class PopulationStatistics extends BaseModel
{
    public function householdCondition(string $alias = 'h'): string
    {
        return $this->notDeletedCondition('households', $alias);
    }

    public function citizenCondition(string $alias = 'c'): string
    {
        return $this->notDeletedCondition('citizens', $alias);
    }

    public function counts(): array
    {
        $householdWhere = $this->householdCondition('h');
        $citizenWhere = $this->citizenCondition('c') . ' AND ' . $this->householdCondition('h');

        $households = $this->fetchOne("SELECT COUNT(*) AS total FROM households h WHERE $householdWhere") ?: [];
        $citizens = $this->fetchOne("SELECT COUNT(*) AS total FROM citizens c INNER JOIN households h ON h.id = c.household_id WHERE $citizenWhere") ?: [];

        return [
            'total_households' => (int) ($households['total'] ?? 0),
            'total_citizens' => (int) ($citizens['total'] ?? 0),
        ];
    }

    private function notDeletedCondition(string $table, string $alias): string
    {
        $conditions = [];
        if ($this->columnExists($table, 'status')) {
            $conditions[] = '(' . $alias . ".status IS NULL OR " . $alias . ".status <> 'DELETED')";
        }
        if ($this->columnExists($table, 'deleted_at')) {
            $conditions[] = $alias . '.deleted_at IS NULL';
        }
        return $conditions ? implode(' AND ', $conditions) : '1=1';
    }
}
