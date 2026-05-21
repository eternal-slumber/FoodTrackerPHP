<?php

declare(strict_types=1);

namespace App\Models;

class MealProduct
{
    public function __construct(
        public int $mealId,
        public string $name,
        public int $weight,
        public string $processing,
        public int $calories,
        public float $proteins = 0,
        public float $fats = 0,
        public float $carbs = 0,
        public ?int $id = null,
        public ?string $createdAt = null
    ) {}

    public static function fromProcessedProduct(int $mealId, array $product): self
    {
        return new self(
            mealId: $mealId,
            name: (string)$product['name'],
            weight: (int)$product['weight'],
            processing: (string)($product['processing'] ?? ''),
            calories: (int)$product['calories'],
            proteins: (float)$product['proteins'],
            fats: (float)$product['fats'],
            carbs: (float)$product['carbs']
        );
    }
}
