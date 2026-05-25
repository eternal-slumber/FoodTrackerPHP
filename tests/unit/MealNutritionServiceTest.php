<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Interfaces\AIServiceInterface;
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
        $this->assertSame(250, $product['calories']);
        $this->assertSame(17.4, $product['proteins']);
        $this->assertSame(18.0, $product['fats']);
        $this->assertSame(3.0, $product['carbs']);
        $this->assertSame(0.88, $product['confidence']);
        $this->assertSame('', $product['processing']);
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

    private function createService(): MealNutritionService
    {
        return new MealNutritionService(
            new class implements AIServiceInterface {
                public function analyze(string $imagePath): array
                {
                    return [];
                }

                public function getProductNutrients(string $productName): array
                {
                    return [
                        'calories' => 50,
                        'proteins' => 1,
                        'fats' => 1,
                        'carbs' => 10,
                    ];
                }

                public function recommendMeal(array $context): string
                {
                    return '';
                }
            },
            new NutritionCalculatorService()
        );
    }
}
