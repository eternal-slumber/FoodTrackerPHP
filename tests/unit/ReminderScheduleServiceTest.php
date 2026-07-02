<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ReminderScheduleRepository;
use App\Services\ReminderScheduleService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class ReminderScheduleServiceTest extends TestCase
{
    public function testSchedulesTomorrowReminderFromUsersLocalMealTime(): void
    {
        $repository = new FakeReminderScheduleRepository();
        $service = new ReminderScheduleService($repository);

        $result = $service->scheduleFromMeal(
            userId: 5,
            mealType: 'breakfast',
            eatenAtUtc: new DateTimeImmutable('2026-07-01 07:00:00', new DateTimeZone('UTC')),
            timezoneOffsetMinutes: -180,
            remindBeforeMinutes: 15
        );

        $this->assertTrue($result['scheduled']);
        $this->assertSame('2026-07-02', $result['local_date']);
        $this->assertSame('2026-07-02T06:45:00Z', $result['send_at']);
        $this->assertSame(['begin', 'setting', 'notification', 'commit'], $repository->events);
        $this->assertSame('10:00:00', $repository->setting['reminder_time']);
        $this->assertSame(-180, $repository->setting['timezone_offset']);
        $this->assertSame('2026-07-02 06:45:00', $repository->notification['send_at']);
    }

    public function testDoesNotLearnFromMealOutsideItsTimeWindow(): void
    {
        $repository = new FakeReminderScheduleRepository();
        $service = new ReminderScheduleService($repository);

        $result = $service->scheduleFromMeal(
            userId: 5,
            mealType: 'dinner',
            eatenAtUtc: new DateTimeImmutable('2026-07-01 07:00:00', new DateTimeZone('UTC')),
            timezoneOffsetMinutes: -180
        );

        $this->assertFalse($result['scheduled']);
        $this->assertSame('outside_meal_window', $result['reason']);
        $this->assertSame([], $repository->events);
    }

    public function testRejectsUnsupportedMealType(): void
    {
        $service = new ReminderScheduleService(new FakeReminderScheduleRepository());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported meal type');

        $service->scheduleFromMeal(
            5,
            'snacks',
            new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone('UTC'))
        );
    }

    public function testRollsBackWhenQueueWriteFails(): void
    {
        $repository = new FakeReminderScheduleRepository(failNotification: true);
        $service = new ReminderScheduleService($repository);

        $this->expectException(\RuntimeException::class);

        try {
            $service->scheduleFromMeal(
                5,
                'lunch',
                new DateTimeImmutable('2026-07-01 12:00:00', new DateTimeZone('UTC'))
            );
        } finally {
            $this->assertSame(['begin', 'setting', 'notification', 'rollback'], $repository->events);
        }
    }

    public function testSchedulesNextDayFromStoredSetting(): void
    {
        $repository = new FakeReminderScheduleRepository();
        $service = new ReminderScheduleService($repository);

        $result = $service->scheduleNextFromSetting(
            userId: 5,
            mealType: 'breakfast',
            reminderTime: '10:00:00',
            remindBeforeMinutes: 15,
            timezoneOffsetMinutes: -180,
            currentLocalDate: '2026-07-02'
        );

        $this->assertSame('2026-07-03', $result['local_date']);
        $this->assertSame('2026-07-03T06:45:00Z', $result['send_at']);
        $this->assertSame('2026-07-03 06:45:00', $repository->notification['send_at']);
    }
}

class FakeReminderScheduleRepository extends ReminderScheduleRepository
{
    /** @var list<string> */
    public array $events = [];
    public array $setting = [];
    public array $notification = [];

    public function __construct(private readonly bool $failNotification = false) {}

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
        $this->setting = [
            'user_id' => $userId,
            'meal_type' => $mealType,
            'reminder_time' => $reminderTime,
            'remind_before_minutes' => $remindBeforeMinutes,
            'timezone_offset' => $timezoneOffsetMinutes,
            'last_meal_at' => $lastMealAtUtc,
        ];
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
        if ($this->failNotification) {
            throw new \RuntimeException('Queue write failed');
        }

        $this->notification = [
            'user_id' => $userId,
            'notification_type' => $notificationType,
            'meal_type' => $mealType,
            'local_date' => $localDate,
            'send_at' => $sendAtUtc,
            'payload' => $payload,
        ];
    }
}
