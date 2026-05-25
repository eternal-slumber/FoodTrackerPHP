<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\AIServiceInterface;
use DateTimeImmutable;
use DateTimeZone;

class MealRecommendationService
{
    public function __construct(
        private readonly DailyNutritionSummaryService $dailySummary,
        private readonly AIServiceInterface $aiService
    ) {}

    public function recommendForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): ?string {
        $summary = $this->dailySummary->getForTelegramUser($telegramId, $timezoneOffsetMinutes);
        if ($summary === null) {
            return null;
        }

        $context = $this->buildContext($summary, $timezoneOffsetMinutes, $nowUtc);
        $recommendation = trim($this->aiService->recommendMeal($context));

        return $recommendation !== '' ? $recommendation : $this->fallbackRecommendation($context);
    }

    public function currentMealType(int $timezoneOffsetMinutes = 0, ?DateTimeImmutable $nowUtc = null): string
    {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localTime = $nowUtc->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes));
        $hour = (int)$localTime->format('G');

        return match (true) {
            $hour >= 5 && $hour < 11 => 'завтрак',
            $hour >= 11 && $hour < 17 => 'обед',
            default => 'ужин',
        };
    }

    private function buildContext(
        array $summary,
        int $timezoneOffsetMinutes,
        ?DateTimeImmutable $nowUtc
    ): array {
        $todayMacros = $summary['today_macros'];
        $macroGoals = $summary['macro_goals'];

        return [
            'meal_type' => $this->currentMealType($timezoneOffsetMinutes, $nowUtc),
            'calories' => [
                'consumed' => (int)$summary['today_sum'],
                'goal' => (int)$summary['daily_goal'],
                'remaining' => (int)$summary['remaining_calories'],
            ],
            'macros' => [
                'proteins' => $this->macroContext($todayMacros['proteins'], $macroGoals['proteins_goal']),
                'fats' => $this->macroContext($todayMacros['fats'], $macroGoals['fats_goal']),
                'carbs' => $this->macroContext($todayMacros['carbs'], $macroGoals['carbs_goal']),
            ],
        ];
    }

    private function macroContext(float|int $current, float|int $goal): array
    {
        return [
            'consumed' => round((float)$current, 1),
            'goal' => round((float)$goal, 1),
            'remaining' => round((float)$goal - (float)$current, 1),
        ];
    }

    private function fallbackRecommendation(array $context): string
    {
        $mealType = $context['meal_type'];
        $remainingCalories = (int)$context['calories']['remaining'];
        $proteinRemaining = (float)$context['macros']['proteins']['remaining'];

        if ($remainingCalories < 250) {
            return "Сейчас лучше выбрать легкий {$mealType}: творог 150 г или греческий йогурт без сахара.\n"
                . "Так ты добавишь белка без сильного перебора по калориям.";
        }

        if ($proteinRemaining > 25) {
            return "Для приема пищи подойдет куриная грудка или рыба с овощами и небольшим гарниром.\n"
                . "Это поможет добрать белок и удержать калории под контролем.";
        }

        return "Для приема пищи подойдет сбалансированная тарелка: белок, овощи и умеренная порция крупы.\n"
            . "Выбирай порцию примерно на " . max(250, min(700, $remainingCalories)) . ' ккал.';
    }
}
