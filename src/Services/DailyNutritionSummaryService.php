<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Goal;
use App\Repositories\UserRepository;
use DateTimeImmutable;

class DailyNutritionSummaryService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MacroGoalCalculationService $macroGoalCalculator
    ) {}

    public function getForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): ?array {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null) {
            return null;
        }

        if ($user->id === null) {
            throw new \RuntimeException('User id is missing');
        }

        $todayNutrition = $this->users->getTodayNutrition($user->id, $timezoneOffsetMinutes, $nowUtc);
        $dailyGoal = $user->dailyGoal ?? 0;
        $macroGoals = $this->macroGoalCalculator->calculate(
            $dailyGoal,
            $user->weight,
            Goal::fromValue($user->goal)
        );

        return [
            'daily_goal' => $dailyGoal,
            'today_sum' => $todayNutrition['calories'],
            'percentage' => $this->calculatePercentage($todayNutrition['calories'], $dailyGoal),
            'remaining_calories' => $dailyGoal - $todayNutrition['calories'],
            'macro_goals' => $macroGoals,
            'today_macros' => [
                'proteins' => $todayNutrition['proteins'],
                'fats' => $todayNutrition['fats'],
                'carbs' => $todayNutrition['carbs'],
            ],
            'macro_percentages' => [
                'proteins' => $this->calculatePercentage($todayNutrition['proteins'], $macroGoals['proteins_goal']),
                'fats' => $this->calculatePercentage($todayNutrition['fats'], $macroGoals['fats_goal']),
                'carbs' => $this->calculatePercentage($todayNutrition['carbs'], $macroGoals['carbs_goal']),
            ],
        ];
    }

    private function calculatePercentage(float|int $current, float|int $goal): float
    {
        return $goal > 0 ? round(((float)$current / (float)$goal) * 100, 1) : 0.0;
    }
}
