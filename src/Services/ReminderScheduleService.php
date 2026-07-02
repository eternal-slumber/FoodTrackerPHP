<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReminderScheduleRepository;
use DateTimeImmutable;
use DateTimeZone;

final class ReminderScheduleService
{
    private const NOTIFICATION_TYPE = 'meal_reminder';

    /** @var array<string, array{start:int, end:int}> */
    private const MEAL_WINDOWS = [
        'breakfast' => ['start' => 5 * 60, 'end' => 11 * 60],
        'lunch' => ['start' => 11 * 60, 'end' => 16 * 60],
        'dinner' => ['start' => 16 * 60, 'end' => 22 * 60],
    ];

    public function __construct(private readonly ReminderScheduleRepository $reminders) {}

    /**
     * @return array{scheduled:bool, reason:?string, meal_type:string, local_date:?string, send_at:?string}
     */
    public function scheduleFromMeal(
        int $userId,
        string $mealType,
        DateTimeImmutable $eatenAtUtc,
        int $timezoneOffsetMinutes = 0,
        int $remindBeforeMinutes = 15
    ): array {
        $mealType = strtolower(trim($mealType));
        $this->assertValidInput($userId, $mealType, $timezoneOffsetMinutes, $remindBeforeMinutes);

        $utc = $eatenAtUtc->setTimezone(new DateTimeZone('UTC'));
        $localMealAt = $utc->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes));

        if (!$this->isInsideMealWindow($mealType, $localMealAt)) {
            return [
                'scheduled' => false,
                'reason' => 'outside_meal_window',
                'meal_type' => $mealType,
                'local_date' => null,
                'send_at' => null,
            ];
        }

        $targetLocalDate = $localMealAt->modify('+1 day');
        $sendAtUtc = $targetLocalDate
            ->modify(sprintf('-%d minutes', $remindBeforeMinutes))
            ->modify(sprintf('%+d minutes', $timezoneOffsetMinutes));

        $this->reminders->beginTransaction();

        try {
            $this->reminders->upsertSetting(
                $userId,
                $mealType,
                $localMealAt->format('H:i:s'),
                $remindBeforeMinutes,
                $timezoneOffsetMinutes,
                $utc->format('Y-m-d H:i:s')
            );
            $this->reminders->upsertNotification(
                $userId,
                self::NOTIFICATION_TYPE,
                $mealType,
                $targetLocalDate->format('Y-m-d'),
                $sendAtUtc->format('Y-m-d H:i:s'),
                ['meal_type' => $mealType]
            );
            $this->reminders->commit();
        } catch (\Throwable $error) {
            $this->reminders->rollBack();
            throw $error;
        }

        return [
            'scheduled' => true,
            'reason' => null,
            'meal_type' => $mealType,
            'local_date' => $targetLocalDate->format('Y-m-d'),
            'send_at' => $sendAtUtc->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return array{local_date:string, send_at:string} */
    public function scheduleNextFromSetting(
        int $userId,
        string $mealType,
        string $reminderTime,
        int $remindBeforeMinutes,
        int $timezoneOffsetMinutes,
        string $currentLocalDate
    ): array {
        $mealType = strtolower(trim($mealType));
        $this->assertValidInput($userId, $mealType, $timezoneOffsetMinutes, $remindBeforeMinutes);

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $reminderTime) !== 1) {
            throw new \InvalidArgumentException('Invalid reminder time');
        }

        $currentLocal = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $currentLocalDate . ' ' . $reminderTime,
            new DateTimeZone('UTC')
        );
        if (!$currentLocal instanceof DateTimeImmutable) {
            throw new \InvalidArgumentException('Invalid local date');
        }

        $nextLocal = $currentLocal->modify('+1 day');
        $sendAtUtc = $nextLocal
            ->modify(sprintf('-%d minutes', $remindBeforeMinutes))
            ->modify(sprintf('%+d minutes', $timezoneOffsetMinutes));

        $this->reminders->upsertNotification(
            $userId,
            self::NOTIFICATION_TYPE,
            $mealType,
            $nextLocal->format('Y-m-d'),
            $sendAtUtc->format('Y-m-d H:i:s'),
            ['meal_type' => $mealType]
        );

        return [
            'local_date' => $nextLocal->format('Y-m-d'),
            'send_at' => $sendAtUtc->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function isInsideMealWindow(string $mealType, DateTimeImmutable $localMealAt): bool
    {
        $minutes = ((int)$localMealAt->format('G') * 60) + (int)$localMealAt->format('i');
        $window = self::MEAL_WINDOWS[$mealType];

        return $minutes >= $window['start'] && $minutes < $window['end'];
    }

    private function assertValidInput(
        int $userId,
        string $mealType,
        int $timezoneOffsetMinutes,
        int $remindBeforeMinutes
    ): void {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user id');
        }

        if (!isset(self::MEAL_WINDOWS[$mealType])) {
            throw new \InvalidArgumentException('Unsupported meal type');
        }

        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        if ($remindBeforeMinutes < 0 || $remindBeforeMinutes > 180) {
            throw new \InvalidArgumentException('Invalid reminder lead time');
        }
    }
}
