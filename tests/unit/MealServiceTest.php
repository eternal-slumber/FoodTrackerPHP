<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Meal;
use App\Models\MealProduct;
use App\Models\User;
use App\Repositories\MealProductRepository;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use App\Services\CalorieCalculatorService;
use App\Services\MealNutritionService;
use App\Services\MealService;
use App\Services\NutritionCalculatorService;
use App\Services\UploadedFileStorage;
use PHPUnit\Framework\TestCase;

class MealServiceTest extends TestCase
{
    public function testSaveManualMealPersistsMealAndProductsInTransaction(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $productRepository = new FakeMealServiceProductRepository();
        $service = $this->createService($mealRepository, $productRepository);

        $result = $service->saveManualMeal(100001, 'Обед', [
            [
                'name' => 'Курица',
                'weight' => 200,
                'processing' => 'grill',
                'kbju' => [
                    'calories' => 100,
                    'proteins' => 20,
                    'fats' => 5,
                    'carbs' => 0,
                ],
            ],
            [
                'name' => 'Рис',
                'weight' => 150,
                'processing' => '',
                'kbju' => [
                    'calories' => 120,
                    'proteins' => 3,
                    'fats' => 1,
                    'carbs' => 25,
                ],
            ],
        ]);

        $this->assertSame(['begin', 'save', 'commit'], $mealRepository->events);
        $this->assertCount(2, $productRepository->savedProducts);
        $this->assertSame(55, $productRepository->savedMealId);
        $this->assertSame('Курица', $productRepository->savedProducts[0]->name);
        $this->assertSame('grill', $productRepository->savedProducts[0]->processing);
        $this->assertSame(55, $result['meal']['id']);
        $this->assertSame(350, $result['meal']['weight']);
    }

    public function testSaveManualMealRollsBackWhenProductsFail(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $productRepository = new FakeMealServiceProductRepository(shouldFailOnSave: true);
        $service = $this->createService($mealRepository, $productRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Product save failed');

        try {
            $service->saveManualMeal(100001, 'Обед', [[
                'name' => 'Курица',
                'weight' => 100,
                'kbju' => ['calories' => 100],
            ]]);
        } finally {
            $this->assertSame(['begin', 'save', 'rollback'], $mealRepository->events);
        }
    }

    public function testGetMealDetailsReturnsProducts(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $mealRepository->meal = new Meal(
            userId: 7,
            description: 'Обед: Курица',
            calories: 250,
            proteins: 40,
            fats: 8,
            carbs: 0,
            totalWeight: 200,
            imagePath: 'user_100001/test.jpg',
            id: 55,
            createdAt: '2026-05-15T11:20:00Z'
        );
        $productRepository = new FakeMealServiceProductRepository(products: [
            new MealProduct(
                mealId: 55,
                name: 'Курица',
                weight: 200,
                processing: 'grill',
                calories: 250,
                proteins: 40,
                fats: 8,
                carbs: 0
            ),
        ]);
        $service = $this->createService($mealRepository, $productRepository);

        $details = $service->getMealDetails(55, 100001);

        $this->assertSame('Обед: Курица', $details['description']);
        $this->assertSame('/api/meals/55/image', $details['image_url']);
        $this->assertSame('Курица', $details['products'][0]['name']);
        $this->assertSame('grill', $details['products'][0]['processing']);
    }

    public function testGetMealThumbnailFallsBackToOriginalWhenThumbnailIsMissing(): void
    {
        $uploadPath = sys_get_temp_dir() . '/foodtracker_meal_service_' . bin2hex(random_bytes(6)) . '/';
        mkdir($uploadPath . 'user_100001', 0777, true);
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/luzNqgAAAABJRU5ErkJggg==');
        file_put_contents($uploadPath . 'user_100001/aaaaaaaa.png', $pngBytes);

        try {
            $mealRepository = new FakeMealServiceMealRepository();
            $mealRepository->meal = new Meal(
                userId: 7,
                description: 'Обед',
                calories: 250,
                imagePath: 'user_100001/aaaaaaaa.png',
                id: 55
            );

            $service = $this->createService(
                $mealRepository,
                new FakeMealServiceProductRepository(),
                new class($uploadPath) extends UploadedFileStorage {
                    public function createThumbnail(string $relativePath): void {}
                }
            );

            $thumbnail = $service->getMealThumbnail(55, 100001);

            $this->assertSame($uploadPath . 'user_100001/aaaaaaaa.png', $thumbnail['path']);
            $this->assertSame('image/png', $thumbnail['mime_type']);
        } finally {
            if (is_file($uploadPath . 'user_100001/aaaaaaaa.png')) {
                unlink($uploadPath . 'user_100001/aaaaaaaa.png');
            }

            if (is_dir($uploadPath . 'user_100001')) {
                rmdir($uploadPath . 'user_100001');
            }

            if (is_dir($uploadPath)) {
                rmdir($uploadPath);
            }
        }
    }

    private function createService(
        FakeMealServiceMealRepository $mealRepository,
        FakeMealServiceProductRepository $productRepository,
        ?UploadedFileStorage $storage = null
    ): MealService {
        return new MealService(
            new FakeMealServiceUserRepository(),
            $mealRepository,
            $productRepository,
            new MealNutritionService(
                new NutritionCalculatorService()
            ),
            $storage ?? new UploadedFileStorage('/tmp/')
        );
    }
}

class FakeMealServiceUserRepository extends UserRepository
{
    public function __construct() {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return new User(
            tgId: $telegramId,
            weight: 70,
            height: 175,
            age: 30,
            gender: 'male',
            dailyGoal: 2000,
            id: 7
        );
    }

    public function getTodayCalories(int $userId, int $timezoneOffsetMinutes = 0): int
    {
        return 500;
    }
}

class FakeMealServiceMealRepository extends MealRepository
{
    public array $events = [];
    public ?Meal $meal = null;

    public function __construct() {}

    public function beginTransaction(): void
    {
        $this->events[] = 'begin';
    }

    public function commit(): void
    {
        $this->events[] = 'commit';
    }

    public function rollBack(): void
    {
        $this->events[] = 'rollback';
    }

    public function save(Meal $meal): bool
    {
        $this->events[] = 'save';
        $meal->id = 55;
        $this->meal = $meal;

        return true;
    }

    public function findById(int $id): ?Meal
    {
        return $this->meal;
    }
}

class FakeMealServiceProductRepository extends MealProductRepository
{
    public ?int $savedMealId = null;
    public array $savedProducts = [];

    public function __construct(
        private readonly bool $shouldFailOnSave = false,
        private readonly array $products = []
    ) {}

    public function saveMany(int $mealId, array $products): void
    {
        if ($this->shouldFailOnSave) {
            throw new \RuntimeException('Product save failed');
        }

        $this->savedMealId = $mealId;
        $this->savedProducts = $products;
    }

    public function findByMealId(int $mealId): array
    {
        return $this->products;
    }
}
