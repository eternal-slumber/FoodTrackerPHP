<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Goal;

class MacroGoalCalculationService
{
    public function calculate(int $dailyCalories, float $weight, Goal $goal): array
    {
        $proteinPerKg = match ($goal) {
            Goal::DEFICIT => 1.8,
            Goal::MAINTENANCE => 1.6,
            Goal::SURPLUS => 1.8,
        };

        $fatRatio = match ($goal) {
            Goal::DEFICIT => 0.25,
            Goal::MAINTENANCE => 0.25,
            Goal::SURPLUS => 0.20,
        };

        $proteinsGoal = (int)round($weight * $proteinPerKg);
        $proteinsCalories = $proteinsGoal * 4;

        $fatsGoal = (int)round(($dailyCalories * $fatRatio) / 9);
        $fatsCalories = $fatsGoal * 9;

        $carbsCalories = max(0, $dailyCalories - $proteinsCalories - $fatsCalories);
        $carbsGoal = (int)round($carbsCalories / 4);

        return [
            'calories_goal' => $dailyCalories,
            'proteins_goal' => $proteinsGoal,
            'fats_goal' => $fatsGoal,
            'carbs_goal' => $carbsGoal,
        ];
    }
}