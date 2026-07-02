<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;

class MealNutritionService
{
    public const MAX_PRODUCTS_PER_MEAL = 6;
    private const KBJU_KEYS = ['calories', 'proteins', 'fats', 'carbs'];

    public function __construct(
        private readonly NutritionCalculatorService $calculator
    ) {}

    public function createAiDraftProduct(array $analysis): array
    {
        $foodName = trim((string)($analysis['food'] ?? 'Не определено'));
        $calories = max(0, (int)($analysis['kcal'] ?? 0));
        $proteins = max(0, round((float)($analysis['proteins'] ?? 0), 1));
        $fats = max(0, round((float)($analysis['fats'] ?? 0), 1));
        $carbs = max(0, round((float)($analysis['carbs'] ?? 0), 1));
        $estimatedWeight = (int)($analysis['weight'] ?? 0);
        $weight = $estimatedWeight > 0
            ? max(1, min(5000, $estimatedWeight))
            : 100;
        $confidence = isset($analysis['confidence'])
            ? max(0, min(1, round((float)$analysis['confidence'], 2)))
            : null;

        return [
            'name' => $foodName !== '' ? $foodName : 'Продукт',
            'weight' => $weight,
            'processing' => '',
            'calories' => $this->portionValueToPer100g($calories, $weight, true),
            'proteins' => $this->portionValueToPer100g($proteins, $weight),
            'fats' => $this->portionValueToPer100g($fats, $weight),
            'carbs' => $this->portionValueToPer100g($carbs, $weight),
            'confidence' => $confidence,
        ];
    }

    public function calculateDraftProductPortion(array $product): array
    {
        return $this->calculator->calculateForPortion([
            'calories' => (float)($product['calories'] ?? 0),
            'proteins' => (float)($product['proteins'] ?? 0),
            'fats' => (float)($product['fats'] ?? 0),
            'carbs' => (float)($product['carbs'] ?? 0),
        ], max(1, min(5000, (int)($product['weight'] ?? 100))));
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

            $this->validateNumericFields($product);
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

    public static function hasMissingKbju(array $product): bool
    {
        $kbju = $product['kbju'] ?? [];
        if (!is_array($kbju)) {
            return true;
        }

        foreach (self::KBJU_KEYS as $key) {
            if (!array_key_exists($key, $kbju) || trim((string)$kbju[$key]) === '') {
                return true;
            }
        }

        return false;
    }

    private function resolveKbju100g(string $name, array $product): array
    {
        $kbju = is_array($product['kbju'] ?? null) ? $product['kbju'] : [];

        return array_reduce(
            self::KBJU_KEYS,
            function (array $resolved, string $key) use ($kbju): array {
                $hasUserValue = array_key_exists($key, $kbju) && trim((string)$kbju[$key]) !== '';
                $resolved[$key] = (float)($hasUserValue ? $kbju[$key] : 0);

                return $resolved;
            },
            []
        );
    }

    private function validateNumericFields(array $product): void
    {
        $weight = $product['weight'] ?? null;
        if ($weight !== null && $weight !== '' && !$this->isNumericInput($weight)) {
            throw new ValidationException('Поле «Вес» должно содержать число');
        }

        $kbju = $product['kbju'] ?? [];
        if (!is_array($kbju)) {
            throw new ValidationException('Поля КБЖУ должны содержать числа');
        }

        $labels = [
            'calories' => 'Ккал',
            'proteins' => 'Белки',
            'fats' => 'Жиры',
            'carbs' => 'Углеводы',
        ];

        foreach ($labels as $key => $label) {
            $value = $kbju[$key] ?? null;
            if ($value !== null && $value !== '' && !$this->isNumericInput($value)) {
                throw new ValidationException(sprintf('Поле «%s» должно содержать число', $label));
            }
        }
    }

    private function isNumericInput(mixed $value): bool
    {
        return (is_int($value) || is_float($value) || is_string($value))
            && is_numeric($value);
    }

    private function portionValueToPer100g(float|int $value, int $weight, bool $integer = false): float|int
    {
        $per100g = $weight > 0 ? ((float)$value * 100 / $weight) : 0;

        return $integer
            ? max(0, (int)round($per100g))
            : max(0, round($per100g, 1));
    }
}
