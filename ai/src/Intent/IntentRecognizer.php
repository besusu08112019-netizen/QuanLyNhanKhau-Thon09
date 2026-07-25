<?php

declare(strict_types=1);

namespace Ai\Intent;

final class IntentRecognizer
{
    private CommandNormalizer $normalizer;

    /**
     * @var list<array{intent:string, category:string, keywords:list<string>, modules:list<string>, confirmation:bool}>
     */
    private array $rules = [
        [
            'intent' => 'navigation.open_module',
            'category' => 'navigation',
            'keywords' => ['mo', 'mở', 'vao', 'vào', 'chuyen', 'chuyển', 'trang', 'module'],
            'modules' => ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'dashboard', 'gis', 'bao cao', 'báo cáo'],
            'confirmation' => false,
        ],
        [
            'intent' => 'search.query',
            'category' => 'search',
            'keywords' => ['tim', 'tìm', 'tra', 'kiem', 'kiếm', 'loc', 'lọc'],
            'modules' => ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'cccd', 'dia chi', 'địa chỉ'],
            'confirmation' => false,
        ],
        [
            'intent' => 'report.view',
            'category' => 'report',
            'keywords' => ['bao cao', 'báo cáo', 'thong ke', 'thống kê', 'tong hop', 'tổng hợp'],
            'modules' => ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'ho ngheo', 'hộ nghèo'],
            'confirmation' => false,
        ],
        [
            'intent' => 'data.create_draft',
            'category' => 'data_entry',
            'keywords' => ['them', 'thêm', 'tao', 'tạo', 'lap', 'lập'],
            'modules' => ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu'],
            'confirmation' => true,
        ],
        [
            'intent' => 'unknown',
            'category' => 'unknown',
            'keywords' => [],
            'modules' => [],
            'confirmation' => false,
        ],
    ];

    public function __construct(?CommandNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new CommandNormalizer();
    }

    public function recognize(string $text): IntentResult
    {
        $command = $this->normalizer->normalize($text);
        if ($command->normalizedText === '') {
            return new IntentResult('unknown', 'unknown', 0.0, $command);
        }

        $bestRule = $this->rules[array_key_last($this->rules)];
        $bestScore = 0.0;
        $bestEntities = [];

        foreach ($this->rules as $rule) {
            if ($rule['intent'] === 'unknown') {
                continue;
            }

            $keywordScore = $this->scorePhrases($command->normalizedText, $rule['keywords']);
            $module = $this->firstMatchingPhrase($command->normalizedText, $rule['modules']);
            $moduleScore = $module === null ? 0.0 : 0.35;
            $score = min(0.99, $keywordScore + $moduleScore);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRule = $rule;
                $bestEntities = $this->extractEntities($command->normalizedText, $module);
            }
        }

        if ($bestScore < 0.45) {
            return new IntentResult('unknown', 'unknown', round($bestScore, 2), $command);
        }

        return new IntentResult(
            $bestRule['intent'],
            $bestRule['category'],
            round($bestScore, 2),
            $command,
            $bestEntities,
            $bestRule['confirmation'],
        );
    }

    /**
     * @param list<string> $phrases
     */
    private function scorePhrases(string $text, array $phrases): float
    {
        $matches = 0;
        foreach ($phrases as $phrase) {
            if ($this->containsPhrase($text, $phrase)) {
                $matches++;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        return min(0.64, 0.32 + ($matches * 0.08));
    }

    /**
     * @param list<string> $phrases
     */
    private function firstMatchingPhrase(string $text, array $phrases): ?string
    {
        foreach ($phrases as $phrase) {
            if ($this->containsPhrase($text, $phrase)) {
                return $phrase;
            }
        }

        return null;
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return str_contains(' ' . $text . ' ', ' ' . mb_strtolower($phrase, 'UTF-8') . ' ');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEntities(string $text, ?string $module): array
    {
        $entities = [];
        if ($module !== null) {
            $entities['module'] = $this->canonicalModule($module);
        }

        if (preg_match('/\b(h\d{2}-\d{4})\b/u', $text, $match)) {
            $entities['household_code'] = strtoupper($match[1]);
        }

        if (preg_match('/\b(?:0|\+84)\d{9,10}\b/u', $text, $match)) {
            $entities['phone'] = $match[0];
        }

        if (preg_match('/\b\d{9}(?:\d{3})?\b/u', $text, $match)) {
            $entities['identity_number'] = $match[0];
        }

        return $entities;
    }

    private function canonicalModule(string $module): string
    {
        return match ($module) {
            'ho dan', 'hộ dân' => 'household',
            'nhan khau', 'nhân khẩu' => 'citizen',
            'bao cao', 'báo cáo' => 'report',
            'ho ngheo', 'hộ nghèo' => 'poor_household',
            default => $module,
        };
    }
}

