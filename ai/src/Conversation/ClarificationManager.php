<?php

declare(strict_types=1);

namespace Ai\Conversation;

use Ai\Intent\IntentResult;

final class ClarificationManager
{
    /**
     * @param array<string, mixed> $memory
     * @return list<string>
     */
    public function missingFields(IntentResult $intent, array $memory = []): array
    {
        if ($intent->intent === 'unknown') {
            return ['intent'];
        }

        $module = $intent->entities['module'] ?? $memory['last_module'] ?? null;
        if (in_array($intent->intent, ['navigation.open_module', 'search.query', 'report.view', 'data.create_draft'], true) && !is_string($module)) {
            return ['module'];
        }

        return [];
    }

    /**
     * @param list<string> $missing
     */
    public function prompt(IntentResult $intent, array $missing): string
    {
        if (in_array('intent', $missing, true)) {
            return 'Toi chua hieu yeu cau. Ban muon mo module, tim kiem hay xem bao cao?';
        }

        if (in_array('module', $missing, true)) {
            return match ($intent->intent) {
                'navigation.open_module' => 'Ban muon mo module nao?',
                'search.query' => 'Ban muon tim trong ho dan hay nhan khau?',
                'report.view' => 'Ban muon xem bao cao ve ho dan hay nhan khau?',
                'data.create_draft' => 'Ban muon them ho dan hay nhan khau?',
                default => 'Ban can bo sung module can xu ly.',
            };
        }

        return '';
    }
}

