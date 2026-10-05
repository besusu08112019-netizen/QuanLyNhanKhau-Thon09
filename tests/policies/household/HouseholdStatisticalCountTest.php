<?php

policy_test('Household totals require a current household head', function (): void {
    $statistics = file_get_contents(BASE_PATH . '/app/Models/PopulationStatistics.php');
    $household = file_get_contents(BASE_PATH . '/app/Models/Household.php');
    $dashboard = file_get_contents(BASE_PATH . '/app/Models/Dashboard.php');
    $report = file_get_contents(BASE_PATH . '/app/Models/Report.php');

    policy_assert_true(is_string($statistics) && is_string($household) && is_string($dashboard) && is_string($report), 'Household statistic sources must be readable.');
    policy_assert_true(str_contains($statistics, 'statisticalHouseholdCondition'), 'Population statistics must define the actual-household condition.');
    policy_assert_true(str_contains($statistics, 'EXISTS (SELECT 1 FROM citizens shc'), 'An actual household must have a current citizen head.');
    policy_assert_true(str_contains($statistics, "currentCitizenCondition('shc')"), 'Moved-out, transferred-out and deceased heads must be excluded.');
    policy_assert_true(str_contains($statistics, "\$householdWhere = \$this->statisticalHouseholdCondition('h');"), 'Top-level household totals must use actual households.');
    policy_assert_true(str_contains($household, "\$reviewFilter ? \$this->activeHouseholdCondition('h') : \$this->statistics()->statisticalHouseholdCondition('h')"), 'Normal lists must show actual households while review filters retain invalid historical rows.');
    policy_assert_true(str_contains($dashboard, "\$where = [\$this->statistics()->statisticalHouseholdCondition('h')];"), 'Dashboard household-only aggregates must use actual households.');
    policy_assert_true(str_contains($report, "\$where = [\$this->statistics()->statisticalHouseholdCondition('h')];"), 'Household reports must use actual households.');
});
