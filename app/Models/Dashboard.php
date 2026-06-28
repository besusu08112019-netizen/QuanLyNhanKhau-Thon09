<?php

namespace App\Models;

use App\Core\BaseModel;

final class Dashboard extends BaseModel
{
    public function summary(): array
    {
        $metrics = $this->fetchOne('SELECT * FROM v_dashboard_summary') ?: [];
        $gender = $this->fetchAll('SELECT gender AS label, COUNT(*) AS value FROM citizens WHERE status <> "DELETED" GROUP BY gender ORDER BY gender');
        $households = $this->fetchAll('SELECT status AS label, COUNT(*) AS value FROM households WHERE status <> "DELETED" GROUP BY status');
        $ages = $this->fetchAll('SELECT CASE WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) <= 5 THEN "0-5" WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) <= 17 THEN "6-17" WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) <= 35 THEN "18-35" WHEN TIMESTAMPDIFF(YEAR,date_of_birth,CURDATE()) <= 59 THEN "36-59" ELSE "60+" END AS label, COUNT(*) AS value FROM citizens WHERE status <> "DELETED" GROUP BY label');
        return ['metrics' => $metrics, 'charts' => ['population' => $gender, 'households' => $households, 'ages' => $ages], 'generatedAt' => date('c')];
    }
}
