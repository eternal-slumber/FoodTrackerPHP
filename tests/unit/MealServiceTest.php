<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Meal;
use App\Models\MealProduct;
use App\Models\User;
use App\Repositories\MealProductRepository;
use App\Repositories\MealRepository;
use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\CalorieCalculatorService;
use App\Services\MealNutritionService;
use App\Services\MealService;
use App\Services\NutritionCalculatorService;
use App\Services\ReminderScheduleService;
use App\Services\UploadedFileStorage;
use DateTimeImmutable;
use DateTimeZone;
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

    public function testSaveManualMealsAsCardsPersistsEachProductAsSeparateMeal(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $productRepository = new FakeMealServiceProductRepository();
        $service = $this->createService(
            $mealRepository,
            $productRepository,
            new class('/tmp/') extends UploadedFileStorage {
                public function sanitizeDraftImagePath(?string $imagePath, int $tgId): ?string
                {
                    return $imagePath;
                }
            }
        );

        $result = $service->saveManualMealsAsCards(100001, 'Ужин: Курица, рис', [
            [
                'name' => 'Курица',
                'weight' => 200,
                'kbju' => ['calories' => 100, 'proteins' => 20, 'fats' => 5, 'carbs' => 0],
            ],
            [
                'name' => 'Рис',
                'weight' => 150,
                'draft_image_path' => 'user_100001/rice.jpg',
                'kbju' => ['calories' => 120, 'proteins' => 3, 'fats' => 1, 'carbs' => 25],
            ],
        ], 'user_100001/main.jpg');

        $this->assertSame(['begin', 'save', 'save', 'commit'], $mealRepository->events);
        $this->assertCount(2, $mealRepository->savedMeals);
        $this->assertSame('Ужин: Курица', $mealRepository->savedMeals[0]->description);
        $this->assertSame('Ужин: Рис', $mealRepository->savedMeals[1]->description);
        $this->assertSame('user_100001/main.jpg', $mealRepository->savedMeals[0]->imagePath);
        $this->assertSame('user_100001/rice.jpg', $mealRepository->savedMeals[1]->imagePath);
        $this->assertCount(2, $productRepository->savedProductBatches);
        $this->assertCount(2, $result['meals']);
    }

    public function testSaveManualMealsAsCardsSchedulesOneReminderAfterCommit(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $reminderRepository = new FakeMealServiceReminderRepository();
        $service = $this->createService(
            $mealRepository,
            new FakeMealServiceProductRepository(),
            reminderSchedule: new ReminderScheduleService($reminderRepository)
        );

        $service->saveManualMealsAsCards(
            100001,
            'Обед: Суп',
            [[
                'name' => 'Суп',
                'weight' => 300,
                'kbju' => ['calories' => 80, 'proteins' => 4, 'fats' => 3, 'carbs' => 10],
            ]],
            mealType: 'lunch',
            eatenAtUtc: new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone('UTC'))
        );

        $this->assertSame(['begin', 'save', 'commit'], $mealRepository->events);
        $this->assertSame(['begin', 'setting', 'notification', 'commit'], $reminderRepository->events);
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

    public function testRejectsOperationsOnMealOwnedByAnotherUser(): void
    {
        $mealRepository = new FakeMealServiceMealRepository();
        $mealRepository->meal = new Meal(
            userId: 8,
            description: 'Чужой приём',
            calories: 250,
            imagePath: 'user_100002/private.jpg',
            id: 55
        );
        $service = $this->createService(
            $mealRepository,
            new FakeMealServiceProductRepository()
        );
        $operations = [
            'details' => fn() => $service->getMealDetails(55, 100001),
            'image' => fn() => $service->getMealImage(55, 100001),
            'thumbnail' => fn() => $service->getMealThumbnail(55, 100001),
            'delete' => fn() => $service->deleteMeal(55, 100001),
        ];

        foreach ($operations as $operation => $run) {
            try {
                $run();
                $this->fail(sprintf('The %s operation allowed access to another user meal', $operation));
            } catch (\RuntimeException $exception) {
                $this->assertSame('Access denied', $exception->getMessage(), $operation);
            }
        }
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
        ?UploadedFileStorage $storage = null,
        ?ReminderScheduleService $reminderSchedule = null
    ): MealService {
        return new MealService(
            new FakeMealServiceUserRepository(),
            $mealRepository,
            $productRepository,
            new MealNutritionService(
                new NutritionCalculatorService()
            ),
            $reminderSchedule ?? new ReminderScheduleService(new FakeMealServiceReminderRepository()),
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
    public array $savedMeals = [];

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
        $this->savedMeals[] = clone $meal;

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
    public array $savedProductBatches = [];

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
        $this->savedProductBatches[] = $products;
    }

    public function findByMealId(int $mealId): array
    {
        return $this->products;
    }
}

class FakeMealServiceReminderRepository extends ReminderScheduleRepository
{
    /** @var list<string> */
    public array $events = [];

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

    public function upsertSetting(
        int $userId,
        string $mealType,
        string $reminderTime,
        int $remindBeforeMinutes,
        int $timezoneOffsetMinutes,
        string $lastMealAtUtc
    ): void {
        $this->events[] = 'setting';
    }

    public function upsertNotification(
        int $userId,
        string $notificationType,
        string $mealType,
        string $localDate,
        string $sendAtUtc,
        array $payload = []
    ): void {
        $this->events[] = 'notification';
    }
}
