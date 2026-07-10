<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\MealRepository;
use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\EveningSummaryScheduleService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class EveningSummaryScheduleServiceTest extends TestCase
{
    public function testSchedulesIncompleteDayForConfiguredEveningTime(): void
    {
        $queue = new FakeEveningSummaryQueueRepository();
        $service = new EveningSummaryScheduleService(
            new FakeEveningSummaryUserRepository(['enabled' => true, 'time' => '21:00']),
            new FakeEveningSummaryMealRepository(['Завтрак']),
            $queue
        );

        $service->scheduleFromMeal(7, $this->utc('2026-07-10 12:00:00'), -180);

        $this->assertSame('evening_summary', $queue->notification['notification_type']);
        $this->assertSame('2026-07-10 18:00:00', $queue->notification['send_at']);
        $this->assertSame(['timezone_offset' => -180], $queue->notification['payload']);
    }

    public function testSchedulesImmediatelyWhenBreakfastLunchAndDinnerExist(): void
    {
        $queue = new FakeEveningSummaryQueueRepository();
        $service = new EveningSummaryScheduleService(
            new FakeEveningSummaryUserRepository(['enabled' => true, 'time' => '21:00']),
            new FakeEveningSummaryMealRepository(['Завтрак', 'Обед', 'Ужин']),
            $queue
        );

        $service->scheduleFromMeal(7, $this->utc('2026-07-10 16:25:00'), -180);

        $this->assertSame('2026-07-10 16:25:00', $queue->notification['send_at']);
    }

    public function testDoesNotScheduleWhenEveningSummaryIsDisabled(): void
    {
        $queue = new FakeEveningSummaryQueueRepository();
        $service = new EveningSummaryScheduleService(
            new FakeEveningSummaryUserRepository(['enabled' => false, 'time' => '21:00']),
            new FakeEveningSummaryMealRepository([]),
            $queue
        );

        $service->scheduleFromMeal(7, $this->utc('2026-07-10 12:00:00'), -180);

        $this->assertSame([], $queue->notification);
    }

    public function testReschedulesPendingSummaryUsingItsStoredTimezone(): void
    {
        $queue = new FakeEveningSummaryQueueRepository();
        $queue->pendingNotifications = [[
            'id' => 15,
            'local_date' => '2026-07-10',
            'payload' => json_encode(['timezone_offset' => -180], JSON_THROW_ON_ERROR),
        ]];
        $service = new EveningSummaryScheduleService(
            new FakeEveningSummaryUserRepository(['enabled' => true, 'time' => '21:00']),
            new FakeEveningSummaryMealRepository([]),
            $queue
        );

        $service->reschedulePendingForUser(7, '22:30');

        $this->assertSame([
            'id' => 15,
            'send_at' => '2026-07-10 19:30:00',
        ], $queue->rescheduledNotification);
    }

    private function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}

class FakeEveningSummaryUserRepository extends UserRepository
{
    public function __construct(private readonly ?array $settings) {}

    public function findEveningSummarySettingsByUserId(int $userId): ?array
    {
        return $this->settings;
    }
}

class FakeEveningSummaryMealRepository extends MealRepository
{
    public function __construct(private readonly array $prefixes) {}

    public function existsForLocalDateWithDescriptionPrefix(
        int $userId,
        string $localDate,
        string $descriptionPrefix,
        int $timezoneOffsetMinutes
    ): bool {
        return in_array($descriptionPrefix, $this->prefixes, true);
    }
}

class FakeEveningSummaryQueueRepository extends ReminderScheduleRepository
{
    public array $notification = [];
    public array $pendingNotifications = [];
    public array $rescheduledNotification = [];

    public function __construct() {}

    public function scheduleEveningSummary(
        int $userId,
        string $localDate,
        string $sendAtUtc,
        int $timezoneOffsetMinutes
    ): void {
        $this->notification = [
            'notification_type' => 'evening_summary',
            'local_date' => $localDate,
            'send_at' => $sendAtUtc,
            'payload' => ['timezone_offset' => $timezoneOffsetMinutes],
        ];
    }

    public function findPendingEveningSummariesForUser(int $userId): array
    {
        return $this->pendingNotifications;
    }

    public function reschedulePendingEveningSummary(int $notificationId, string $sendAtUtc): void
    {
        $this->rescheduledNotification = [
            'id' => $notificationId,
            'send_at' => $sendAtUtc,
        ];
    }
}
