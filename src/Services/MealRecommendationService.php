<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

class MealRecommendationService
{
    public function __construct(
        private readonly DailyNutritionInsightService $dailyInsight
    ) {}

    public function recommendForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): ?string {
        try {
            $result = $this->dailyInsight->refreshForTelegramUser(
                $telegramId,
                $timezoneOffsetMinutes,
                $nowUtc
            );
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (($result['state'] ?? null) === 'empty') {
            return 'Добавь первый прием пищи в приложении — после него я проанализирую дневной баланс и предложу конкретные блюда.';
        }

        $insight = is_array($result['insight'] ?? null) ? $result['insight'] : [];
        $nextMeal = is_array($insight['next_meal'] ?? null) ? $insight['next_meal'] : [];

        return $this->formatRecommendation($nextMeal);
    }

    private function formatRecommendation(array $nextMeal): string
    {
        $mealType = trim((string)($nextMeal['type'] ?? 'следующий прием'));
        $advice = trim((string)($nextMeal['advice'] ?? ''));
        $targetCalories = max(0, (int)($nextMeal['target_calories'] ?? 0));
        $foods = array_values(array_filter(
            (array)($nextMeal['foods'] ?? []),
            static fn(mixed $food): bool => is_string($food) && trim($food) !== ''
        ));
        $lines = ['Что можно съесть сейчас (' . $mealType . '):'];

        if ($advice !== '') {
            $lines[] = '';
            $lines[] = $advice;
        }

        foreach (array_slice($foods, 0, 3) as $index => $food) {
            $lines[] = '';
            $lines[] = ($index + 1) . '. ' . trim($food);
        }

        if ($targetCalories > 0) {
            $lines[] = '';
            $lines[] = "Ориентир на прием: около {$targetCalories} ккал.";
        }

        return implode("\n", $lines);
    }
}
