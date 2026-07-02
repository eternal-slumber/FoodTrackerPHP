<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\TelegramBotConfig;
use App\Repositories\MealRepository;
use App\Repositories\ReminderScheduleRepository;
use App\Services\NotificationDispatchService;
use App\Services\ReminderScheduleService;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class NotificationDispatchServiceTest extends TestCase
{
    public function testSendsDueReminderAndSchedulesNextDay(): void
    {
        $repository = new FakeNotificationDispatchRepository([$this->notification()]);
        $client = new FakeNotificationTelegramClient();
        $service = $this->createService($repository, $client, mealExists: false);

        $result = $service->dispatchDue($this->now());

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['next_scheduled']);
        $this->assertSame(['sent:10', 'next:2026-07-03'], $repository->events);
        $this->assertSame('900001', $client->messages[0]['chat_id']);
        $this->assertStringContainsString('завтрак', $client->messages[0]['text']);
        $this->assertSame('Открыть Mini App', $client->messages[0]['reply_markup']['inline_keyboard'][0][0]['text']);
    }

    public function testSkipsReminderWhenMealAlreadyExistsAndSchedulesNextDay(): void
    {
        $repository = new FakeNotificationDispatchRepository([$this->notification()]);
        $client = new FakeNotificationTelegramClient();
        $service = $this->createService($repository, $client, mealExists: true);

        $result = $service->dispatchDue($this->now());

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['next_scheduled']);
        $this->assertSame([], $client->messages);
        $this->assertSame(['skipped:10', 'next:2026-07-03'], $repository->events);
    }

    public function testDisabledSettingIsSkippedWithoutNextNotification(): void
    {
        $notification = $this->notification();
        $notification['setting_enabled'] = 0;
        $repository = new FakeNotificationDispatchRepository([$notification]);
        $client = new FakeNotificationTelegramClient();
        $service = $this->createService($repository, $client, mealExists: false);

        $result = $service->dispatchDue($this->now());

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['next_scheduled']);
        $this->assertSame(['skipped:10'], $repository->events);
        $this->assertSame([], $client->messages);
    }

    public function testTelegramFailureMarksNotificationFailedAndKeepsScheduleAlive(): void
    {
        $repository = new FakeNotificationDispatchRepository([$this->notification()]);
        $client = new FakeNotificationTelegramClient(shouldFail: true);
        $service = $this->createService($repository, $client, mealExists: false);

        $result = $service->dispatchDue($this->now());

        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['next_scheduled']);
        $this->assertSame(['failed:10', 'next:2026-07-03'], $repository->events);
    }

    private function createService(
        FakeNotificationDispatchRepository $repository,
        FakeNotificationTelegramClient $client,
        bool $mealExists
    ): NotificationDispatchService {
        return new NotificationDispatchService(
            $repository,
            new ReminderScheduleService($repository),
            new FakeNotificationMealRepository($mealExists),
            $client,
            new TelegramBotMessageFactory(),
            new TelegramBotConfig('token', 'secret', 'https://example.com', -180)
        );
    }

    /** @return array<string, int|string> */
    private function notification(): array
    {
        return [
            'id' => 10,
            'user_id' => 5,
            'telegram_id' => '900001',
            'notification_type' => 'meal_reminder',
            'meal_type' => 'breakfast',
            'local_date' => '2026-07-02',
            'send_at' => '2026-07-02 06:45:00',
            'attempts' => 0,
            'reminder_time' => '10:00:00',
            'remind_before_minutes' => 15,
            'timezone_offset' => -180,
            'setting_enabled' => 1,
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-02 06:45:00', new DateTimeZone('UTC'));
    }
}

class FakeNotificationDispatchRepository extends ReminderScheduleRepository
{
    /** @var list<array<string, mixed>> */
    private array $notifications;
    /** @var list<string> */
    public array $events = [];

    public function __construct(array $notifications)
    {
        $this->notifications = $notifications;
    }

    public function claimDueNotifications(string $nowUtc, string $staleProcessingBeforeUtc, int $limit = 50): array
    {
        return $this->notifications;
    }

    public function markSent(int $notificationId, string $sentAtUtc): void
    {
        $this->events[] = 'sent:' . $notificationId;
    }

    public function markSkipped(int $notificationId): void
    {
        $this->events[] = 'skipped:' . $notificationId;
    }

    public function markFailed(int $notificationId, string $errorMessage): void
    {
        $this->events[] = 'failed:' . $notificationId;
    }

    public function upsertNotification(
        int $userId,
        string $notificationType,
        string $mealType,
        string $localDate,
        string $sendAtUtc,
        array $payload = []
    ): void {
        $this->events[] = 'next:' . $localDate;
    }
}

class FakeNotificationMealRepository extends MealRepository
{
    public function __construct(private readonly bool $mealExists) {}

    public function existsForLocalDateWithDescriptionPrefix(
        int $userId,
        string $localDate,
        string $descriptionPrefix,
        int $timezoneOffsetMinutes
    ): bool {
        return $this->mealExists;
    }
}

class FakeNotificationTelegramClient implements TelegramBotClientInterface
{
    public array $messages = [];

    public function __construct(private readonly bool $shouldFail = false) {}

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('Telegram unavailable');
        }

        $this->messages[] = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $replyMarkup,
        ];

        return ['message_id' => 1];
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void {}

    public function answerCallbackQuery(string $callbackQueryId): void {}
}
