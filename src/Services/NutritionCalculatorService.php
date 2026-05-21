<?php

declare(strict_types=1);

namespace App\Services;

class NutritionCalculatorService
{
    private const PROCESSING_OPTIONS = [
        ['value' => '', 'label' => 'Не указано - КБЖУ готового продукта', 'coefficient' => 1.0],
        ['value' => 'fry', 'label' => 'Жарка', 'coefficient' => 1.4],
        ['value' => 'bake', 'label' => 'Запекание', 'coefficient' => 1.3],
        ['value' => 'boil', 'label' => 'Варка', 'coefficient' => 1.2],
        ['value' => 'stew', 'label' => 'Тушение', 'coefficient' => 1.1],
        ['value' => 'grill', 'label' => 'Гриль', 'coefficient' => 1.25],
        ['value' => 'steam', 'label' => 'На пару', 'coefficient' => 1.05],
        ['value' => 'deep_fry', 'label' => 'Фритюр', 'coefficient' => 1.6],
        ['value' => 'no_oil_fry', 'label' => 'Жарка без масла', 'coefficient' => 1.15],
    ];

    public function applyProcessingCoefficient(float $baseValue, string $processing): float
    {
        $coefficient = $this->processingCoefficient($processing);
        return $baseValue * $coefficient;
    }

    public function processingCoefficient(string $processing): float
    {
        foreach (self::PROCESSING_OPTIONS as $option) {
            if ($option['value'] === $processing) {
                return $option['coefficient'];
            }
        }

        return 1.0;
    }

    public function getProcessingOptions(): array
    {
        return self::PROCESSING_OPTIONS;
    }

    public function calculateForPortion(array $kbju100g, int $weight): array
    {
        $ratio = $weight / 100;
        
        return [
            'calories' => (int)round($kbju100g['calories'] * $ratio),
            'proteins' => round($kbju100g['proteins'] * $ratio, 1),
            'fats' => round($kbju100g['fats'] * $ratio, 1),
            'carbs' => round($kbju100g['carbs'] * $ratio, 1),
        ];
    }

    public function sumProducts(array $products): array
    {
        $total = [
            'calories' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbs' => 0,
        ];

        foreach ($products as $product) {
            $total['calories'] += $product['calories'];
            $total['proteins'] += $product['proteins'];
            $total['fats'] += $product['fats'];
            $total['carbs'] += $product['carbs'];
        }

        return [
            'calories' => (int)round($total['calories']),
            'proteins' => round($total['proteins'], 1),
            'fats' => round($total['fats'], 1),
            'carbs' => round($total['carbs'], 1),
        ];
    }

    public function calculateMeal(array $products): array
    {
        $calculated = [];

        foreach ($products as $product) {
            $kbju = $this->calculateForPortion(
                [
                    'calories' => (float)($product['kbju']['calories'] ?? 0),
                    'proteins' => (float)($product['kbju']['proteins'] ?? 0),
                    'fats' => (float)($product['kbju']['fats'] ?? 0),
                    'carbs' => (float)($product['kbju']['carbs'] ?? 0),
                ],
                (int)$product['weight']
            );

            $calculated[] = [
                'name' => $product['name'],
                'weight' => $product['weight'],
                'processing' => $product['processing'],
                'calories' => $kbju['calories'],
                'proteins' => $kbju['proteins'],
                'fats' => $kbju['fats'],
                'carbs' => $kbju['carbs'],
            ];
        }

        return $this->sumProducts($calculated);
    }
}
