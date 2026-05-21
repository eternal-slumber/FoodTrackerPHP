<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Interfaces\AIServiceInterface;

class MealNutritionService
{
    public const MAX_PRODUCTS_PER_MEAL = 6;

    public function __construct(
        private readonly AIServiceInterface $aiService,
        private readonly NutritionCalculatorService $calculator
    ) {}

    public function createAiDraftProduct(array $analysis): array
    {
        $foodName = trim((string)($analysis['food'] ?? 'Не определено'));
        $calories = max(0, (int)($analysis['kcal'] ?? 0));
        $proteins = max(0, round((float)($analysis['proteins'] ?? 0), 1));
        $fats = max(0, round((float)($analysis['fats'] ?? 0), 1));
        $carbs = max(0, round((float)($analysis['carbs'] ?? 0), 1));
        $confidence = isset($analysis['confidence'])
            ? max(0, min(1, round((float)$analysis['confidence'], 2)))
            : null;

        return [
            'name' => $foodName !== '' ? $foodName : 'Продукт',
            'weight' => 100,
            'processing' => '',
            'calories' => $calories,
            'proteins' => $proteins,
            'fats' => $fats,
            'carbs' => $carbs,
            'confidence' => $confidence,
        ];
    }

    public function processProducts(array $products): array
    {
        if (count($products) > self::MAX_PRODUCTS_PER_MEAL) {
            throw new ValidationException('В одном приеме можно сохранить не больше ' . self::MAX_PRODUCTS_PER_MEAL . ' продуктов');
        }

        $processedProducts = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $name = substr(trim((string)($product['name'] ?? '')), 0, 120);
            if ($name === '') {
                continue;
            }

            $weight = max(1, min(5000, (int)($product['weight'] ?? 100)));
            $processing = (string)($product['processing'] ?? '');
            $kbju100g = $this->resolveKbju100g($name, $product);

            foreach (['calories', 'proteins', 'fats', 'carbs'] as $key) {
                $kbju100g[$key] = $this->calculator->applyProcessingCoefficient($kbju100g[$key], $processing);
            }

            $kbjuPortion = $this->calculator->calculateForPortion($kbju100g, $weight);

            $processedProducts[] = [
                'name' => $name,
                'weight' => $weight,
                'processing' => $processing,
                'calories' => $kbjuPortion['calories'],
                'proteins' => $kbjuPortion['proteins'],
                'fats' => $kbjuPortion['fats'],
                'carbs' => $kbjuPortion['carbs'],
            ];
        }

        if ($processedProducts === []) {
            throw new ValidationException('No valid products to save');
        }

        return $processedProducts;
    }

    public function sumProducts(array $products): array
    {
        return $this->calculator->sumProducts($products);
    }

    public function sumWeight(array $products): int
    {
        return array_reduce(
            $products,
            fn(int $total, array $product): int => $total + (int)($product['weight'] ?? 0),
            0
        );
    }

    private function resolveKbju100g(string $name, array $product): array
    {
        if (!empty($product['kbju']['calories'])) {
            return [
                'calories' => (float)$product['kbju']['calories'],
                'proteins' => (float)($product['kbju']['proteins'] ?? 0),
                'fats' => (float)($product['kbju']['fats'] ?? 0),
                'carbs' => (float)($product['kbju']['carbs'] ?? 0),
            ];
        }

        $aiKbju = $this->aiService->getProductNutrients($name);

        return [
            'calories' => (float)($aiKbju['calories'] ?? 0),
            'proteins' => (float)($aiKbju['proteins'] ?? 0),
            'fats' => (float)($aiKbju['fats'] ?? 0),
            'carbs' => (float)($aiKbju['carbs'] ?? 0),
        ];
    }
}
