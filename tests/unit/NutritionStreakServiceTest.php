<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MealRepository;
use App\Services\NutritionStreakService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class NutritionStreakServiceTest extends TestCase
{
    public function testCountsConsecutiveDaysIncludingToday(): void
    {
        $service = $this->createService([
            '2026-06-27',
            '2026-06-26',
            '2026-06-25',
            '2026-06-23',
        ]);

        $result = $service->getForUser(7, 0, $this->utc('2026-06-27 12:00:00'));

        $this->assertSame(3, $result['current_days']);
        $this->assertTrue($result['today_completed']);
    }

    public function testKeepsYesterdayStreakUntilCurrentDayEnds(): void
    {
        $service = $this->createService(['2026-06-26', '2026-06-25']);

        $result = $service->getForUser(7, 0, $this->utc('2026-06-27 12:00:00'));

        $this->assertSame(2, $result['current_days']);
        $this->assertFalse($result['today_completed']);
    }

    public function testReturnsZeroAfterMissedDay(): void
    {
        $service = $this->createService(['2026-06-25', '2026-06-24']);

        $result = $service->getForUser(7, 0, $this->utc('2026-06-27 12:00:00'));

        $this->assertSame(0, $result['current_days']);
        $this->assertFalse($result['today_completed']);
    }

    public function testCountsEachDateOnlyOnce(): void
    {
        $service = $this->createService(['2026-06-27', '2026-06-27', '2026-06-26']);

        $result = $service->getForUser(7, 0, $this->utc('2026-06-27 12:00:00'));

        $this->assertSame(2, $result['current_days']);
    }

    public function testUsesClientTimezoneOffsetForLocalToday(): void
    {
        $service = $this->createService(['2026-06-28']);

        $result = $service->getForUser(7, -180, $this->utc('2026-06-27 21:30:00'));

        $this->assertSame(1, $result['current_days']);
        $this->assertTrue($result['today_completed']);
    }

    public function testRejectsInvalidTimezoneOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timezone offset');

        $this->createService([])->getForUser(7, 900, $this->utc('2026-06-27 12:00:00'));
    }

    private function createService(array $activeDates): NutritionStreakService
    {
        return new NutritionStreakService(new FakeStreakMealRepository($activeDates));
    }

    private function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}

final class FakeStreakMealRepository extends MealRepository
{
    public function __construct(private readonly array $activeDates) {}

    public function findActiveMealDates(int $userId, int $timezoneOffsetMinutes = 0): array
    {
        return $this->activeDates;
    }
}
