<?php

$model = file_get_contents(__DIR__ . '/../app/Models/Report.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ReportController.php');

$checks = [
    [$model, 'awayForWorkHouseholdsReport($filters)', 'report route'],
    [$model, "\$filters['householdStatus'] = 'away_for_work'", 'forced status filter'],
    [$model, 'v.village_id=h.village_id', 'tenant-safe count join'],
    [$model, "report_head.relationship='Chủ hộ'", 'current household head fallback'],
    [$model, 'report_head.village_id=$householdAlias.village_id', 'tenant-safe household head'],
    [$model, 'BÁO CÁO HỘ ĐI LÀM ĂN XA', 'report title'],
    [$model, "'away-for-work-households'", 'report center registration'],
    [$controller, "'away-for-work-households'", 'permission mapping'],
];
foreach ($checks as [$source, $needle, $label]) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FAIL: {$label}\n"); exit(1);
    }
}
echo "away-for-work-household-report: OK\n";
