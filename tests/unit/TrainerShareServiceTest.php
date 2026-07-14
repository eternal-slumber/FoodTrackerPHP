<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\DailyNutritionInsightRepository;
use App\Repositories\MealRepository;
use App\Repositories\SharedAccessLinkRepository;
use App\Repositories\UserRepository;
use App\Services\MacroGoalCalculationService;
use App\Services\TrainerShareService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class TrainerShareServiceTest extends TestCase
{
    public function testCreatesThirtyDayProtectedLink(): void
    {
        $links = new FakeTrainerShareLinkRepository();
        $service = $this->service($links);

        $result = $service->createForOwner(100001, 'arbuz', -180, $this->now());

        $this->assertTrue($result['active']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);
        $this->assertSame('2026-08-09 12:00:00', $result['expires_at']);
        $this->assertSame(-180, $result['timezone_offset']);
    }

    public function testPublicDiaryContainsNutritionButNotPrivateBodyParameters(): void
    {
        $links = new FakeTrainerShareLinkRepository();
        $links->publicLink = [
            'id' => 4,
            'user_id' => 7,
            'display_name' => 'arbuz',
            'timezone_offset' => -180,
            'expires_at' => '2026-08-09 12:00:00',
            'daily_goal' => 2000,
            'weight' => 70,
            'goal' => 'maintenance',
        ];
        $service = $this->service($links);

        $diary = $service->getPublicDiary(str_repeat('a', 64), $this->now());

        $this->assertNotNull($diary);
        $this->assertSame('arbuz', $diary['display_name']);
        $this->assertSame(596, $diary['average_7_days']);
        $this->assertSame(1200, $diary['summary']['calories']);
        $this->assertArrayNotHasKey('weight', $diary);
        $this->assertArrayNotHasKey('telegram_id', $diary);
        $this->assertSame('Белок в норме.', $diary['ai_analysis']['summary']);
        $this->assertSame(4, $links->viewedId);
    }

    public function testUpdatesDurationAndVisibilityWithoutChangingToken(): void
    {
        $links = new FakeTrainerShareLinkRepository();
        $links->activeLink = [
            'token' => str_repeat('b', 64),
            'expires_at' => '2026-08-09 12:00:00',
            'duration_days' => 30,
            'last_viewed_at' => null,
        ];
        $service = $this->service($links);

        $result = $service->updateForOwner(100001, 'unlimited', [
            'meals' => true,
            'nutrition' => true,
            'history' => false,
            'ai_analysis' => false,
            'profile_params' => false,
        ], $this->now());

        $this->assertSame(str_repeat('b', 64), $result['token']);
        $this->assertSame('unlimited', $result['duration']);
        $this->assertNull($result['expires_at']);
        $this->assertFalse($result['visibility']['history']);
        $this->assertNull($links->updated['expires_at']);
    }

    private function service(FakeTrainerShareLinkRepository $links): TrainerShareService
    {
        return new TrainerShareService(
            new FakeTrainerShareUserRepository(),
            new FakeTrainerShareMealRepository(),
            new FakeTrainerShareInsightRepository(),
            $links,
            new MacroGoalCalculationService()
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
    }
}

class FakeTrainerShareUserRepository extends UserRepository
{
    public function __construct() {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return new User($telegramId, 70, 175, 30, 'male', dailyGoal: 2000, id: 7);
    }

    public function getTodayNutrition(int $userId, int $timezoneOffsetMinutes = 0, ?DateTimeImmutable $nowUtc = null): array
    {
        return ['calories' => 1200, 'proteins' => 80.0, 'fats' => 50.0, 'carbs' => 130.0];
    }
}

class FakeTrainerShareMealRepository extends MealRepository
{
    public function __construct() {}

    public function findForLocalDay(int $userId, int $timezoneOffsetMinutes = 0, ?DateTimeImmutable $nowUtc = null): array
    {
        return [['id' => 1, 'name' => 'Завтрак', 'time' => '09:00', 'calories' => 500, 'proteins' => 20, 'fats' => 15, 'carbs' => 60]];
    }

    public function getDailyCaloriesForMonth(int $userId, string $month, int $timezoneOffsetMinutes): array
    {
        return $month === '2026-07' ? [
            ['date' => '2026-07-09', 'calories' => 500, 'meals' => []],
            ['date' => '2026-07-10', 'calories' => 692, 'meals' => []],
        ] : [];
    }
}

class FakeTrainerShareInsightRepository extends DailyNutritionInsightRepository
{
    public function __construct() {}

    public function findForUserAndDate(int $userId, string $localDate): ?array
    {
        return ['short_summary' => 'Белок в норме.', 'day_analysis' => 'Рацион выглядит сбалансированным.'];
    }
}

class FakeTrainerShareLinkRepository extends SharedAccessLinkRepository
{
    public ?array $publicLink = null;
    public ?array $activeLink = null;
    public array $updated = [];
    public ?int $viewedId = null;

    public function __construct() {}

    public function findActiveForUser(int $userId, string $nowUtc): ?array
    {
        return $this->activeLink;
    }

    public function findActiveByToken(string $token, string $nowUtc): ?array
    {
        return $this->publicLink;
    }

    public function replaceForUser(
        int $userId,
        string $token,
        string $displayName,
        int $timezoneOffset,
        ?string $expiresAtUtc,
        string $nowUtc,
        ?int $durationDays,
        array $visibility
    ): array
    {
        return [
            'token' => $token,
            'display_name' => $displayName,
            'timezone_offset' => $timezoneOffset,
            'duration_days' => $durationDays,
            'visibility' => $visibility,
            'expires_at' => $expiresAtUtc,
        ];
    }

    public function updateActiveForUser(int $userId, ?string $expiresAtUtc, ?int $durationDays, array $visibility): void
    {
        $this->updated = ['expires_at' => $expiresAtUtc, 'duration_days' => $durationDays, 'visibility' => $visibility];
    }

    public function revokeForUser(int $userId, string $nowUtc): void {}

    public function touchViewed(int $id, string $viewedAtUtc): void
    {
        $this->viewedId = $id;
    }
}
