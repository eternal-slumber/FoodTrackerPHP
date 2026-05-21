<?php 

declare(strict_types=1);

namespace App\Models;

class Meal
{
    public function __construct(
        public int $userId,
        public string $description,
        public int $calories,
        public float $proteins = 0,
        public float $fats = 0,
        public float $carbs = 0,
        public ?int $totalWeight = null,
        public ?string $imagePath = null,
        public ?int $id = null,
        public ?string $createdAt = null
    ) {}
}
