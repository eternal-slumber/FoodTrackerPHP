<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\DailyNutritionInsightAIService;
use App\Config\AIProviderConfig;
use App\Models\User;
use App\Repositories\DailyNutritionInsightRepository;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use App\Services\DailyNutritionInsightService;
use App\Services\DailyNutritionSummaryService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class DailyNutritionInsightServiceTest extends TestCase
{
    public function testRefreshCachesOneInsightUntilNutritionContextChanges(): void
    {
        $meals = new FakeInsightMealRepository([[
            'id' => 10,
            'name' => 'Обед: курица с рисом',
            'time' => '14:20',
            'calories' => 650,
            'proteins' => 45.0,
            'fats' => 18.0,
            'carbs' => 70.0,
        ]]);
        $repository = new FakeDailyNutritionInsightRepository();
        $ai = new FakeDailyNutritionInsightAIService();
        $service = $this->createService($meals, $repository, $ai);
        $now = new DateTimeImmutable('2026-06-27 18:00:00', new DateTimeZone('UTC'));

        $missing = $service->getForTelegramUser(100001, -180, $now);
        $generated = $service->refreshForTelegramUser(100001, -180, $now);
        $cached = $service->refreshForTelegramUser(100001, -180, $now);

        $this->assertSame('missing', $missing['state']);
        $this->assertSame('ready', $generated['state']);
        $this->assertFalse($generated['cached']);
        $this->assertTrue($cached['cached']);
        $this->assertSame(1, $ai->calls);
        $this->assertSame('ужин', $ai->context['next_meal_type']);

        $meals->items[] = [
            'id' => 11,
            'name' => 'Перекус: йогурт',
            'time' => '21:15',
            'calories' => 180,
            'proteins' => 15.0,
            'fats' => 4.0,
            'carbs' => 20.0,
        ];

        $this->assertSame('stale', $service->getForTelegramUser(100001, -180, $now)['state']);
    }

    public function testReturnsEmptyWithoutCallingAiWhenThereAreNoMeals(): void
    {
        $repository = new FakeDailyNutritionInsightRepository();
        $ai = new FakeDailyNutritionInsightAIService();
        $service = $this->createService(new FakeInsightMealRepository([]), $repository, $ai);
        $now = new DateTimeImmutable('2026-06-27 10:00:00', new DateTimeZone('UTC'));

        $result = $service->refreshForTelegramUser(100001, 0, $now);

        $this->assertSame('empty', $result['state']);
        $this->assertSame(0, $ai->calls);
        $this->assertTrue($repository->deleted);
    }

    private function createService(
        FakeInsightMealRepository $meals,
        FakeDailyNutritionInsightRepository $repository,
        FakeDailyNutritionInsightAIService $ai
    ): DailyNutritionInsightService {
        return new DailyNutritionInsightService(
            new FakeInsightUserRepository(),
            $meals,
            new FakeInsightSummaryService(),
            $repository,
            $ai,
            AIProviderConfig::fromEnv([
                'AI_PROVIDER' => 'openrouter',
                'AI_BASE_URL' => 'https://openrouter.ai/api/v1',
                'AI_API_KEY' => 'test',
                'AI_MODEL' => 'vision-model',
                'AI_TEXT_MODEL' => 'small-text-model',
                'AI_VISION_MODEL' => 'vision-model',
            ])
        );
    }
}

class FakeInsightUserRepository extends UserRepository
{
    public function __construct() {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return new User(
            tgId: $telegramId,
            weight: 72,
            height: 178,
            age: 30,
            gender: 'male',
            goal: 'maintenance',
            dailyGoal: 2200,
            id: 7
        );
    }
}

class FakeInsightMealRepository extends MealRepository
{
    public function __construct(public array $items) {}

    public function findForLocalDay(
        int $userId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        return $this->items;
    }
}

class FakeInsightSummaryService extends DailyNutritionSummaryService
{
    public function __construct() {}

    public function getForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): ?array {
        return [
            'daily_goal' => 2200,
            'today_sum' => 1450,
            'remaining_calories' => 750,
            'today_macros' => ['proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0],
            'macro_goals' => ['proteins_goal' => 115, 'fats_goal' => 65, 'carbs_goal' => 260],
        ];
    }
}

class FakeDailyNutritionInsightRepository extends DailyNutritionInsightRepository
{
    public ?array $stored = null;
    public bool $deleted = false;

    public function __construct() {}

    public function findForUserAndDate(int $userId, string $localDate): ?array
    {
        return $this->stored;
    }

    public function save(
        int $userId,
        string $localDate,
        int $timezoneOffsetMinutes,
        string $contextHash,
        array $insight,
        string $model,
        string $generatedAt
    ): void {
        $this->stored = [
            'user_id' => $userId,
            'local_date' => $localDate,
            'timezone_offset' => $timezoneOffsetMinutes,
            'context_hash' => $contextHash,
            'short_summary' => $insight['short_summary'],
            'day_analysis' => $insight['day_analysis'],
            'next_meal' => $insight['next_meal'],
            'model' => $model,
            'generated_at' => $generatedAt,
        ];
    }

    public function deleteForUserAndDate(int $userId, string $localDate): void
    {
        $this->deleted = true;
        $this->stored = null;
    }
}

class FakeDailyNutritionInsightAIService extends DailyNutritionInsightAIService
{
    public int $calls = 0;
    public array $context = [];

    public function __construct() {}

    public function generate(array $context): array
    {
        $this->calls++;
        $this->context = $context;

        return [
            'short_summary' => 'Стоит добрать белок.',
            'day_analysis' => 'Калорийность пока в пределах нормы. Жиров уже достаточно.',
            'next_meal' => [
                'type' => $context['next_meal_type'],
                'advice' => 'Выберите нежирный белок с овощами.',
                'target_calories' => 450,
                'foods' => ['рыба', 'овощи'],
            ],
        ];
    }
}
