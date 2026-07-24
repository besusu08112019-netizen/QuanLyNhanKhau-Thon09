<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\SystemInsight;

final class InsightController extends BaseController
{
    private SystemInsight $insights;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->insights = new SystemInsight();
    }

    public function search(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $q = trim((string) $this->query('q', $this->query('search', '')));
        $limit = (int) $this->query('limit', 20);
        $this->ok($this->insights->globalSearch($q, $limit));
    }

    public function alerts(): void
    {
        $this->requirePermission('dashboard', 'read');
        $this->requirePermission('household', 'read');
        $this->requirePermission('citizen', 'read');
        $this->ok($this->insights->smartAlerts());
    }

    public function ask(): void
    {
        $user = $this->requirePermission('dashboard', 'read');
        $question = trim((string)($this->input()['question'] ?? $this->query('q', '')));
        foreach ($this->insights->requiredModulesForQuestion($question) as $module) {
            $this->requirePermission($module, 'read');
        }
        $answer = $this->insights->ask($question);
        $this->audit($user, 'insights', 'ask_readonly', 'Hoi tro ly du lieu chi doc', null, ['intent' => $answer['intent'] ?? 'overview']);
        $this->ok($answer);
    }
}
