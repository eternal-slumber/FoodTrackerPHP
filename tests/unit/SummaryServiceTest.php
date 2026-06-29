<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use App\Services\MacroGoalCalculationService;
use App\Services\SummaryService;
use PHPUnit\Framework\TestCase;

class SummaryServiceTest extends TestCase
{
    public function testMonthlySummaryMapsPercentagesToColors(): void
    {
        $service = $this->createService([
            ['date' => '2026-05-01', 'calories' => 1000],
            ['date' => '2026-05-02', 'calories' => 1600],
            ['date' => '2026-05-03', 'calories' => 2000],
            ['date' => '2026-05-04', 'calories' => 2200],
            ['date' => '2026-05-05', 'calories' => 2600],
        ]);

        $summary = $service->getMonthlySummary(100001, '2026-05', -180);

        $this->assertSame('low', $summary['days'][0]['color']);
        $this->assertSame(50.0, $summary['days'][0]['percentage']);
        $this->assertSame('warning', $summary['days'][1]['color']);
        $this->assertSame('good', $summary['days'][2]['color']);
        $this->assertSame('over', $summary['days'][3]['color']);
        $this->assertSame('danger', $summary['days'][4]['color']);
    }

    public function testMonthlySummaryKeepsTotalsAndMeals(): void
    {
        $service = $this->createService([
            [
                'date' => '2026-05-14',
                'calories' => 1840,
                'proteins' => 120.55,
                'fats' => 60.24,
                'carbs' => 180.04,
                'weight' => 900,
                'meals' => [
                    [
                        'id' => 12,
                        'description' => 'Омлет',
                        'calories' => 420,
                        'proteins' => 28.0,
                        'fats' => 25.0,
                        'carbs' => 12.0,
                        'weight' => 250,
                        'time' => '09:30',
                        'thumbnail_url' => '/api/meals/12/thumbnail',
                    ],
                ],
            ],
        ]);

        $day = $service->getMonthlySummary(100001, '2026-05', -180)['days'][0];

        $this->assertSame(120.6, $day['proteins']);
        $this->assertSame(60.2, $day['fats']);
        $this->assertSame(180.0, $day['carbs']);
        $this->assertSame(900, $day['weight']);
        $this->assertSame('Омлет', $day['meals'][0]['description']);
        $this->assertSame('09:30', $day['meals'][0]['time']);
        $this->assertSame('/api/meals/12/thumbnail', $day['meals'][0]['thumbnail_url']);
    }

    public function testEmptyMonthReturnsNoDays(): void
    {
        $service = $this->createService([]);

        $summary = $service->getMonthlySummary(100001, '2026-05', 0);

        $this->assertSame('2026-05', $summary['month']);
        $this->assertSame(2000, $summary['daily_goal']);
        $this->assertSame([
            'calories_goal' => 2000,
            'proteins_goal' => 112,
            'fats_goal' => 56,
            'carbs_goal' => 262,
        ], $summary['macro_goals']);
        $this->assertSame([], $summary['days']);
    }

    public function testInvalidMonthThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid month');

        $this->createService([])->getMonthlySummary(100001, '2026-5', 0);
    }

    private function createService(array $dailyCalories): SummaryService
    {
        $user = new User(
            tgId: 100001,
            weight: 70,
            height: 175,
            age: 30,
            gender: 'male',
            dailyGoal: 2000,
            id: 7
        );

        return new SummaryService(
            new FakeSummaryUserRepository($user),
            new FakeSummaryMealRepository($dailyCalories),
            new MacroGoalCalculationService()
        );
    }
}

class FakeSummaryUserRepository extends UserRepository
{
    public function __construct(private readonly ?User $user) {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return $this->user;
    }
}

class FakeSummaryMealRepository extends MealRepository
{
    public function __construct(private readonly array $dailyCalories) {}

    public function getDailyCaloriesForMonth(int $userId, string $month, int $timezoneOffsetMinutes): array
    {
        return $this->dailyCalories;
    }
}
