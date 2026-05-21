<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public int $tgId,
        public float $weight,
        public int $height,
        public int $age,
        public string $gender,
        public string $activityLevel = 'medium',
        public string $goal = 'maintenance',
        public ?int $dailyGoal = null,
        public ?int $id = null
    ) {}
}
