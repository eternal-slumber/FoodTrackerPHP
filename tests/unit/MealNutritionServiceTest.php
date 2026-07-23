<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\MealNutritionService;
use App\Services\NutritionCalculatorService;
use PHPUnit\Framework\TestCase;

class MealNutritionServiceTest extends TestCase
{
    public function testProcessProductsUsesReadyProductNutritionAndPortion(): void
    {
        $service = $this->createService();

        $products = $service->processProducts([
            [
                'name' => 'Суп',
                'weight' => 300,
                'processing' => 'boil',
                'kbju' => [
                    'calories' => 50,
                    'proteins' => 3,
                    'fats' => 2,
                    'carbs' => 5,
                ],
            ],
            [
                'name' => 'Картофель фри',
                'weight' => 200,
                'processing' => 'deep_fry',
                'kbju' => [
                    'calories' => 100,
                    'proteins' => 2,
                    'fats' => 1,
                    'carbs' => 20,
                ],
            ],
        ]);

        $this->assertSame('', $products[0]['processing']);
        $this->assertSame('deep_fry', $products[1]['processing']);
        $this->assertSame(200, $products[1]['calories']);
        $this->assertSame(4.0, $products[1]['proteins']);
        $this->assertSame(2.0, $products[1]['fats']);
        $this->assertSame(40.0, $products[1]['carbs']);
    }

    public function testCreateAiDraftProductUsesNoProcessingByDefault(): void
    {
        $service = $this->createService();

        $product = $service->createAiDraftProduct([
            'food' => 'Омлет',
            'kcal' => 250,
            'proteins' => 17.35,
            'fats' => 18.04,
            'carbs' => 3.01,
            'confidence' => 0.876,
        ]);

        $this->assertSame('Омлет', $product['name']);
        $this->assertSame(100, $product['weight']);
        $this->assertSame(250, $product['calories']);
        $this->assertSame(17.4, $product['proteins']);
        $this->assertSame(18.0, $product['fats']);
        $this->assertSame(3.0, $product['carbs']);
        $this->assertSame(0.88, $product['confidence']);
        $this->assertSame('', $product['processing']);
    }

    public function testCreateAiDraftProductConvertsPortionEstimateToPer100g(): void
    {
        $service = $this->createService();

        $product = $service->createAiDraftProduct([
            'food' => 'Паста с курицей',
            'weight' => 250,
            'kcal' => 520,
            'proteins' => 32.5,
            'fats' => 18.0,
            'carbs' => 54.0,
        ]);

        $this->assertSame('Паста с курицей', $product['name']);
        $this->assertSame(250, $product['weight']);
        $this->assertSame(208, $product['calories']);
        $this->assertSame(13.0, $product['proteins']);
        $this->assertSame(7.2, $product['fats']);
        $this->assertSame(21.6, $product['carbs']);
        $this->assertSame([
            'calories' => 520,
            'proteins' => 32.5,
            'fats' => 18.0,
            'carbs' => 54.0,
        ], $service->calculateDraftProductPortion($product));
    }

    public function testProcessProductsRejectsTooManyProducts(): void
    {
        $service = $this->createService();
        $products = array_fill(0, MealNutritionService::MAX_PRODUCTS_PER_MEAL + 1, [
            'name' => 'Продукт',
            'weight' => 100,
            'kbju' => ['calories' => 100],
        ]);

        $this->expectException(ValidationException::class);

        $service->processProducts($products);
    }

    public function testProcessProductsUsesZeroForMissingKbjuFields(): void
    {
        $service = $this->createService();

        $products = $service->processProducts([[
            'name' => 'Творог',
            'weight' => 100,
            'processing' => '',
            'kbju' => [
                'calories' => 120,
                'proteins' => '',
                'fats' => 4,
                'carbs' => '',
            ],
        ]]);

        $this->assertSame(120, $products[0]['calories']);
        $this->assertSame(0.0, $products[0]['proteins']);
        $this->assertSame(4.0, $products[0]['fats']);
        $this->assertSame(0.0, $products[0]['carbs']);
    }

    public function testProcessProductsRejectsTextInWeightField(): void
    {
        $service = $this->createService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Поле «Вес» должно содержать число');

        $service->processProducts([[
            'name' => 'Творог',
            'weight' => 'сто',
            'kbju' => ['calories' => 120],
        ]]);
    }

    public function testProcessProductsRejectsTextInKbjuField(): void
    {
        $service = $this->createService();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Поле «Белки» должно содержать число');

        $service->processProducts([[
            'name' => 'Творог',
            'weight' => 100,
            'kbju' => ['calories' => 120, 'proteins' => 'много'],
        ]]);
    }

    public function testProcessProductsRejectsOutOfRangeNumbers(): void
    {
        $cases = [
            [['weight' => 5001], 'Поле «Вес» должно быть не больше 5000'],
            [['kbju' => ['calories' => -1]], 'Поле «Ккал» должно быть не меньше 0'],
            [['kbju' => ['calories' => 1001]], 'Поле «Ккал» должно быть не больше 1000'],
            [['kbju' => ['proteins' => 101]], 'Поле «Белки» должно быть не больше 100'],
            [['kbju' => ['fats' => '1e309']], 'Поле «Жиры» должно содержать корректное число'],
        ];

        foreach ($cases as [$override, $expectedMessage]) {
            $product = array_replace_recursive([
                'name' => 'Продукт',
                'weight' => 100,
                'kbju' => [
                    'calories' => 100,
                    'proteins' => 10,
                    'fats' => 10,
                    'carbs' => 10,
                ],
            ], $override);

            try {
                $this->createService()->processProducts([$product]);
                $this->fail('Expected invalid nutrition value to be rejected');
            } catch (ValidationException $error) {
                $this->assertSame($expectedMessage, $error->getMessage());
            }
        }
    }

    public function testHasMissingKbjuTreatsZeroAsFilledValue(): void
    {
        $this->assertFalse(MealNutritionService::hasMissingKbju([
            'kbju' => [
                'calories' => '100',
                'proteins' => '0',
                'fats' => '0',
                'carbs' => '0',
            ],
        ]));

        $this->assertTrue(MealNutritionService::hasMissingKbju([
            'kbju' => [
                'calories' => '100',
                'proteins' => '',
                'fats' => '0',
                'carbs' => '0',
            ],
        ]));
    }

    private function createService(): MealNutritionService
    {
        return new MealNutritionService(
            new NutritionCalculatorService()
        );
    }
}
