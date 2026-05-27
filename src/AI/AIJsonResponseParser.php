<?php

declare(strict_types=1);

namespace App\AI;

class AIJsonResponseParser
{
    public function parseObject(string $text): ?array
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $cleaned, $matches) !== 1) {
            return null;
        }

        $parsed = json_decode($matches[0], true);

        return is_array($parsed) ? $parsed : null;
    }
}
