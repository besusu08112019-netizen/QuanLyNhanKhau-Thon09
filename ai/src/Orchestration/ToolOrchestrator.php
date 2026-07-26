<?php

declare(strict_types=1);

namespace Ai\Orchestration;

use Ai\Core\ToolRegistry;
use Ai\Intent\CommandNormalizer;
use Ai\Tools\ToolExecutor;

final class ToolOrchestrator
{
    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ToolExecutor $executor,
        private readonly CommandNormalizer $normalizer = new CommandNormalizer(),
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function ask(string $question, array $context = []): array
    {
        $plan = $this->plan($question);
        if (($plan['status'] ?? '') !== 'planned') {
            return $plan;
        }

        $result = $this->executor->execute($plan['tool'], $plan['input'], $context);
        return [
            'status' => $result->ok ? 'answered' : 'failed',
            'mode' => 'READ_ONLY',
            'question' => trim($question),
            'plan' => $plan,
            'result' => $result->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(string $question): array
    {
        $command = $this->normalizer->normalize($question);
        $text = $this->fold($command->normalizedText);
        if ($text === '') {
            return $this->clarify('question', 'Hay nhap cau hoi can tra cuu.');
        }

        if (preg_match('/\b(h\d{2}-\d{4})\b/i', $text, $match)) {
            return $this->planned('household', ['action' => 'find_by_code', 'code' => strtoupper($match[1])], 0.95, 'household_code');
        }

        if (preg_match('/\b\d{9}(?:\d{3})?\b/', $text, $match) && $this->hasAny($text, ['cccd', 'can cuoc', 'dinh danh', 'nhan khau', 'cong dan'])) {
            return $this->planned('resident', ['action' => 'find_by_identity', 'identity' => $match[0]], 0.92, 'identity_number');
        }

        if ($this->hasAny($text, ['chua dong', 'no quy', 'dong quy', 'phan anh', 'chua xu ly', 'dang xu ly', 'bao tri', 'bao duong', 'vat nuoi', 'bien dong', 'thang nay'])) {
            return $this->planned('insight', ['action' => 'ask', 'question' => trim($question)], 0.88, 'operational_insight');
        }

        if ($this->hasAny($text, ['bhyt', 'bao hiem y te', 'bao hiem'])) {
            return $this->planned('statistics', ['action' => 'health_insurance'], 0.9, 'health_insurance_stats');
        }

        if ($this->hasAny($text, ['thong ke', 'bao cao', 'tong hop', 'dashboard'])) {
            return $this->planned('statistics', ['action' => 'summary'], 0.84, 'statistics_summary');
        }

        if ($this->hasAny($text, ['tong so', 'so luong', 'bao nhieu'])) {
            return $this->planned('statistics', ['action' => 'counts'], 0.82, 'counts');
        }

        if ($this->hasAny($text, ['tren 80', 'hon 80', 'cao tuoi'])) {
            return $this->planned('resident', ['action' => 'list', 'ageFrom' => 80, 'pageSize' => 20], 0.78, 'elderly_residents');
        }

        if ($this->hasAny($text, ['nhan khau', 'cong dan', 'cu dan'])) {
            return $this->planned('resident', ['action' => 'list', 'search' => $this->searchText($text, ['tim', 'kiem', 'tra', 'nhan khau', 'cong dan', 'cu dan']), 'pageSize' => 20], 0.72, 'resident_list');
        }

        if ($this->hasAny($text, ['ho dan', 'ho gia dinh'])) {
            return $this->planned('household', ['action' => 'list', 'search' => $this->searchText($text, ['tim', 'kiem', 'tra', 'ho dan', 'ho gia dinh']), 'pageSize' => 20], 0.72, 'household_list');
        }

        return $this->clarify('intent', 'Toi chua xac dinh duoc can tra cuu ho dan, nhan khau hay thong ke.');
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function planned(string $tool, array $input, float $confidence, string $reason): array
    {
        if (!$this->registry->has($tool)) {
            return [
                'status' => 'failed',
                'mode' => 'READ_ONLY',
                'error' => 'tool_not_registered',
                'tool' => $tool,
            ];
        }

        return [
            'status' => 'planned',
            'mode' => 'READ_ONLY',
            'tool' => $tool,
            'input' => $input,
            'confidence' => $confidence,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clarify(string $missing, string $prompt): array
    {
        return [
            'status' => 'needs_clarification',
            'mode' => 'READ_ONLY',
            'missing' => [$missing],
            'prompt' => $prompt,
        ];
    }

    /**
     * @param list<string> $needles
     */
    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains(' ' . $text . ' ', ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $remove
     */
    private function searchText(string $text, array $remove): string
    {
        foreach ($remove as $word) {
            $text = trim(str_replace(' ' . $word . ' ', ' ', ' ' . $text . ' '));
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function fold(string $text): string
    {
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $folded = is_string($folded) && $folded !== '' ? $folded : $text;
        $folded = strtolower($folded);
        $folded = preg_replace('/[^a-z0-9\s\-]+/', ' ', $folded) ?? $folded;
        $folded = preg_replace('/\s+/', ' ', $folded) ?? $folded;
        return trim($folded);
    }
}
