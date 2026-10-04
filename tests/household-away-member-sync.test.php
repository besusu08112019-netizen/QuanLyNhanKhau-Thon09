<?php

$source = file_get_contents(__DIR__ . '/../app/Models/Household.php');

$expectations = [
    'transaction starts' => '$this->db->beginTransaction()',
    'transaction commits' => '$this->db->commit()',
    'transaction rolls back' => '$this->db->rollBack()',
    'explicit away status only' => "(\$params['residence_status'] ?? '') === 'away_for_work'",
    'manual status only' => "(\$params['residence_status_mode'] ?? '') === 'MANUAL'",
    'members become away' => 'UPDATE citizens SET presence_status="AWAY"',
    'deleted members excluded' => 'status <> "DELETED"',
    'deceased members excluded' => 'COALESCE(life_status,"ALIVE") <> "DECEASED"',
    'transferred members excluded' => 'COALESCE(residency_status,"PERMANENT") <> "TRANSFERRED_OUT"',
    'moved-out members excluded' => 'COALESCE(presence_status,"AT_HOME") <> "MOVED_OUT"',
    'tenant scoped' => "tenantWhere('citizens')",
];

foreach ($expectations as $label => $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

if (str_contains($source, 'UPDATE citizens SET presence_status="AWAY", residency_status')) {
    fwrite(STDERR, "FAIL: member synchronization must preserve residency_status\n");
    exit(1);
}

if (preg_match('/UPDATE citizens SET presence_status=[\"\x27]AT_HOME[\"\x27]/', $source)) {
    fwrite(STDERR, "FAIL: household status changes must not force members back home\n");
    exit(1);
}

echo "household-away-member-sync: OK\n";
