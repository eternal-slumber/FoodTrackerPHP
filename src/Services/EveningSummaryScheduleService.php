<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MealRepository;
use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

class EveningSummaryScheduleService
{
    private const MEAL_PREFIXES = ['Завтрак', 'Обед', 'Ужин'];

    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly ReminderScheduleRepository $reminders
    ) {}

    public function scheduleFromMeal(
        int $userId,
        DateTimeImmutable $eatenAtUtc,
        int $timezoneOffsetMinutes
    ): void {
        $this->assertTimezoneOffset($timezoneOffsetMinutes);

        $settings = $this->users->findEveningSummarySettingsByUserId($userId);
        if ($settings === null || !$settings['enabled']) {
            return;
        }

        $nowUtc = $eatenAtUtc->setTimezone(new DateTimeZone('UTC'));
        $localDate = $nowUtc
            ->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes))
            ->format('Y-m-d');
        $sendAtUtc = $this->allMainMealsAdded($userId, $localDate, $timezoneOffsetMinutes)
            ? $nowUtc
            : $this->scheduledTimeUtc($localDate, $settings['time'], $timezoneOffsetMinutes);

        $this->reminders->scheduleEveningSummary(
            $userId,
            $localDate,
            $sendAtUtc->format('Y-m-d H:i:s'),
            $timezoneOffsetMinutes
        );
    }

    public function reschedulePendingForUser(int $userId, string $time): void
    {
        foreach ($this->reminders->findPendingEveningSummariesForUser($userId) as $notification) {
            $payload = json_decode(
                (string)($notification['payload'] ?? ''),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $timezoneOffsetMinutes = $payload['timezone_offset'] ?? null;
            if (!is_int($timezoneOffsetMinutes)) {
                throw new \RuntimeException('Evening summary timezone offset is missing');
            }
            $this->assertTimezoneOffset($timezoneOffsetMinutes);

            $sendAtUtc = $this->scheduledTimeUtc(
                (string)$notification['local_date'],
                $time,
                $timezoneOffsetMinutes
            );
            $this->reminders->reschedulePendingEveningSummary(
                (int)$notification['id'],
                $sendAtUtc->format('Y-m-d H:i:s')
            );
        }
    }

    private function allMainMealsAdded(int $userId, string $localDate, int $timezoneOffsetMinutes): bool
    {
        foreach (self::MEAL_PREFIXES as $prefix) {
            if (!$this->meals->existsForLocalDateWithDescriptionPrefix(
                $userId,
                $localDate,
                $prefix,
                $timezoneOffsetMinutes
            )) {
                return false;
            }
        }

        return true;
    }

    private function scheduledTimeUtc(string $localDate, string $time, int $timezoneOffsetMinutes): DateTimeImmutable
    {
        $local = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $localDate . ' ' . $time,
            new DateTimeZone('UTC')
        );
        if (!$local instanceof DateTimeImmutable) {
            throw new \InvalidArgumentException('Invalid evening summary time');
        }

        return $local->modify(sprintf('%+d minutes', $timezoneOffsetMinutes));
    }

    private function assertTimezoneOffset(int $timezoneOffsetMinutes): void
    {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }
    }
}
