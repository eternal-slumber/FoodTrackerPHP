<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Services\MealNutritionService;
use App\Services\NutritionCalculatorService;
use PHPUnit\Framework\TestCase;

class MealNutritionServiceTest extends TestCase
{
    public function testProcessProductsAppliesProcessingAndPortion(): void
    {
        $service = $this->createService();

        $products = $service->processProducts([[
            'name' => 'Картофель',
            'weight' => 200,
            'processing' => 'deep_fry',
            'kbju' => [
                'calories' => 100,
                'proteins' => 2,
                'fats' => 1,
                'carbs' => 20,
            ],
        ]]);

        $this->assertSame(320, $products[0]['calories']);
        $this->assertSame(6.4, $products[0]['proteins']);
        $this->assertSame(3.2, $products[0]['fats']);
        $this->assertSame(64.0, $products[0]['carbs']);
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
