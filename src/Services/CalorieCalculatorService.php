<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Enums\ActivityLevel;
use App\Enums\Goal;
use App\ValueObjects\BodyMetrics;

class CalorieCalculatorService
{

    public function calculateBMR(BodyMetrics $metrics): int
    {
        $base = (10 * $metrics->weight) + (6.25 * $metrics->height) - (5 * $metrics->age);

        return (int)($base + $metrics->gender->getBmrAdjustment());
    }

    public function calculateTDEE(int $bmr, ActivityLevel $activityLevel): int
    {
        return (int)($bmr * $activityLevel->getFactor());
    }

    public function calculateDailyCalories(int $tdee, Goal $goal): int
    {
        return (int)($tdee * $goal->getModifier());
    }

    public function calculate(BodyMetrics $metrics, ActivityLevel $activityLevel, Goal $goal): int
    {
        $bmr = $this->calculateBMR($metrics);
        $tdee = $this->calculateTDEE($bmr, $activityLevel);
        return $this->calculateDailyCalories($tdee, $goal);
    }

}