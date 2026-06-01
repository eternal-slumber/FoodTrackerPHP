<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Goal;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;

class SummaryService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly MacroGoalCalculationService $macroGoalCalculator
    ) {}

    public function getMonthlySummary(int $telegramId, string $month, int $timezoneOffsetMinutes): array
    {
        if (!$this->isValidMonth($month)) {
            throw new InvalidArgumentException('Invalid month');
        }

        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new InvalidArgumentException('Invalid timezone offset');
        }

        $user = $this->users->findByTelegramId($telegramId);
        if (!$user || !$user->id) {
            throw new InvalidArgumentException('User not found');
        }

        $dailyGoal = (int)($user->dailyGoal ?? 0);
        $macroGoals = $this->macroGoalCalculator->calculate(
            $dailyGoal,
            (float)$user->weight,
            Goal::fromValue($user->goal)
        );
        $days = array_map(
            fn(array $day): array => $this->formatDay($day, $dailyGoal),
            $this->meals->getDailyCaloriesForMonth((int)$user->id, $month, $timezoneOffsetMinutes)
        );

        return [
            'month' => $month,
            'daily_goal' => $dailyGoal,
            'macro_goals' => $macroGoals,
            'days' => $days,
        ];
    }

    private function formatDay(array $day, int $dailyGoal): array
    {
        $calories = (int)($day['calories'] ?? 0);
        $percentage = $dailyGoal > 0 ? round(($calories / $dailyGoal) * 100, 1) : 0.0;

        return [
            'date' => (string)$day['date'],
            'calories' => $calories,
            'percentage' => $percentage,
            'color' => $this->resolveColor($percentage, $calories),
            'proteins' => round((float)($day['proteins'] ?? 0), 1),
            'fats' => round((float)($day['fats'] ?? 0), 1),
            'carbs' => round((float)($day['carbs'] ?? 0), 1),
            'weight' => (int)($day['weight'] ?? 0),
            'meals' => $day['meals'] ?? [],
        ];
    }

    private function resolveColor(float $percentage, int $calories): string
    {
        if ($calories <= 0) {
            return 'empty';
        }

        if ($percentage < 70) {
            return 'low';
        }

        if ($percentage < 90) {
            return 'warning';
        }

        if ($percentage <= 105) {
            return 'good';
        }

        if ($percentage <= 120) {
            return 'over';
        }

        return 'danger';
    }

    private function isValidMonth(string $month): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }
}
