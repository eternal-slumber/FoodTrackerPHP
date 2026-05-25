<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DailyNutritionSummaryService;
use App\Services\MacroGoalCalculationService;
use PHPUnit\Framework\TestCase;

class DailyNutritionSummaryServiceTest extends TestCase
{
    public function testBuildsTodaySummaryFromUserAndNutrition(): void
    {
        $service = new DailyNutritionSummaryService(
            new FakeDailySummaryUserRepository(
                new User(
                    tgId: 100001,
                    weight: 72,
                    height: 180,
                    age: 30,
                    gender: 'male',
                    activityLevel: 'medium',
                    goal: 'maintenance',
                    dailyGoal: 2200,
                    id: 7
                ),
                ['calories' => 1450, 'proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0]
            ),
            new MacroGoalCalculationService()
        );

        $summary = $service->getForTelegramUser(100001, -180);

        $this->assertNotNull($summary);
        $this->assertSame(1450, $summary['today_sum']);
        $this->assertSame(2200, $summary['daily_goal']);
        $this->assertSame(750, $summary['remaining_calories']);
        $this->assertSame(82.0, $summary['today_macros']['proteins']);
        $this->assertSame(115, $summary['macro_goals']['proteins_goal']);
    }

    public function testReturnsNullWhenUserIsMissing(): void
    {
        $service = new DailyNutritionSummaryService(
            new FakeDailySummaryUserRepository(null, []),
            new MacroGoalCalculationService()
        );

        $this->assertNull($service->getForTelegramUser(100001));
    }
}

class FakeDailySummaryUserRepository extends UserRepository
{
    public function __construct(
        private readonly ?User $user,
        private readonly array $nutrition
    ) {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return $this->user;
    }

    public function getTodayNutrition(
        int $userId,
        int $timezoneOffsetMinutes = 0,
        ?\DateTimeImmutable $nowUtc = null
    ): array {
        return $this->nutrition;
    }
}
