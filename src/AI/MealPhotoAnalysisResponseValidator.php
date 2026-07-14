<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\AIInvalidResponseException;

final class MealPhotoAnalysisResponseValidator
{
    private const MAX_FOOD_NAME_LENGTH = 120;
    private const MAX_WEIGHT = 10_000;
    private const MAX_KCAL = 10_000;
    private const MAX_MACRONUTRIENT = 1_000;
    private const ALLOWED_FIELDS = [
        'food',
        'weight',
        'kcal',
        'proteins',
        'fats',
        'carbs',
        'confidence',
    ];

    /**
     * @param array<array-key, mixed> $analysis
     * @return array{
     *     food: string,
     *     weight: int,
     *     kcal: int,
     *     proteins: float,
     *     fats: float,
     *     carbs: float,
     *     confidence: float
     * }
     */
    public function validateAndNormalize(array $analysis): array
    {
        $unknownFields = array_diff(array_keys($analysis), self::ALLOWED_FIELDS);
        if ($unknownFields !== []) {
            $this->invalidResponse();
        }

        $food = $this->requiredFoodName($analysis);

        return [
            'food' => $food,
            'weight' => (int)round($this->requiredNumber($analysis, 'weight', self::MAX_WEIGHT)),
            'kcal' => (int)round($this->requiredNumber($analysis, 'kcal', self::MAX_KCAL)),
            'proteins' => round($this->requiredNumber($analysis, 'proteins', self::MAX_MACRONUTRIENT), 1),
            'fats' => round($this->requiredNumber($analysis, 'fats', self::MAX_MACRONUTRIENT), 1),
            'carbs' => round($this->requiredNumber($analysis, 'carbs', self::MAX_MACRONUTRIENT), 1),
            'confidence' => round($this->requiredNumber($analysis, 'confidence', 1), 2),
        ];
    }

    /** @param array<array-key, mixed> $analysis */
    private function requiredFoodName(array $analysis): string
    {
        if (!array_key_exists('food', $analysis) || !is_string($analysis['food'])) {
            $this->invalidResponse();
        }

        $food = trim(preg_replace('/\s+/u', ' ', $analysis['food']) ?? '');
        if ($food === '' || $this->unicodeLength($food) > self::MAX_FOOD_NAME_LENGTH) {
            $this->invalidResponse();
        }

        return $food;
    }

    /** @param array<array-key, mixed> $analysis */
    private function requiredNumber(array $analysis, string $field, float $maximum): float
    {
        if (!array_key_exists($field, $analysis)) {
            $this->invalidResponse();
        }

        $value = $analysis[$field];
        if (
            !is_int($value)
            && !is_float($value)
            && !(is_string($value) && $value !== '' && is_numeric($value))
        ) {
            $this->invalidResponse();
        }

        $number = (float)$value;
        if (!is_finite($number) || $number < 0 || $number > $maximum) {
            $this->invalidResponse();
        }

        return $number;
    }

    private function unicodeLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        $result = preg_match_all('/./us', $value, $matches);

        return $result === false ? strlen($value) : $result;
    }

    private function invalidResponse(): never
    {
        throw new AIInvalidResponseException('AI вернул некорректный результат анализа', 502);
    }
}
