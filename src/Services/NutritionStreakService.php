<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MealRepository;
use DateTimeImmutable;
use DateTimeZone;

final class NutritionStreakService
{
    public function __construct(private readonly MealRepository $meals) {}

    /** @return array{current_days:int, today_completed:bool} */
    public function getForUser(
        int $userId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $this->assertValidTimezoneOffset($timezoneOffsetMinutes);

        $today = $this->getLocalDate($timezoneOffsetMinutes, $nowUtc);
        $activeDates = array_fill_keys(
            $this->meals->findActiveMealDates($userId, $timezoneOffsetMinutes),
            true
        );
        $todayCompleted = isset($activeDates[$today]);
        $cursor = new DateTimeImmutable($today, new DateTimeZone('UTC'));

        if (!$todayCompleted) {
            $cursor = $cursor->modify('-1 day');
        }

        $currentDays = 0;
        while (isset($activeDates[$cursor->format('Y-m-d')])) {
            $currentDays++;
            $cursor = $cursor->modify('-1 day');
        }

        return [
            'current_days' => $currentDays,
            'today_completed' => $todayCompleted,
        ];
    }

    private function getLocalDate(int $timezoneOffsetMinutes, ?DateTimeImmutable $nowUtc): string
    {
        $utc = ($nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));

        return $utc
            ->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes))
            ->format('Y-m-d');
    }

    private function assertValidTimezoneOffset(int $timezoneOffsetMinutes): void
    {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }
    }
}
