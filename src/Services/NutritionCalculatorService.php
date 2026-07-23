<?php

declare(strict_types=1);

namespace App\Services;

class NutritionCalculatorService
{
    private const PROCESSING_OPTIONS = [
        ['value' => '', 'label' => 'Не указано - КБЖУ готового продукта'],
        ['value' => 'fry', 'label' => 'Жарка'],
        ['value' => 'bake', 'label' => 'Запекание'],
        ['value' => 'boil', 'label' => 'Варка'],
        ['value' => 'stew', 'label' => 'Тушение'],
        ['value' => 'grill', 'label' => 'Гриль'],
        ['value' => 'steam', 'label' => 'На пару'],
        ['value' => 'deep_fry', 'label' => 'Фритюр'],
        ['value' => 'no_oil_fry', 'label' => 'Жарка без масла'],
    ];

    public function normalizeProcessing(string $processing): string
    {
        $processing = trim($processing);

        foreach (self::PROCESSING_OPTIONS as $option) {
            if ($option['value'] === $processing) {
                return $processing;
            }
        }

        return '';
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
