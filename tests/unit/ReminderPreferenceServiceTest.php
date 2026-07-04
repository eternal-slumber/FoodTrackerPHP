<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;
use App\Services\ReminderPreferenceService;
use PHPUnit\Framework\TestCase;

class ReminderPreferenceServiceTest extends TestCase
{
    public function testDisablingPreferenceUpdatesSettingsAndPendingQueue(): void
    {
        $users = new FakeReminderPreferenceUserRepository();
        $reminders = new FakeReminderPreferenceRepository();
        $service = new ReminderPreferenceService($users, $reminders);

        $enabled = $service->updateForTelegramUser(100001, false);

        $this->assertFalse($enabled);
        $this->assertFalse($users->enabled);
        $this->assertSame(
            ['begin', 'settings:0', 'skip-pending', 'commit'],
            $reminders->events
        );
    }

    public function testEnablingPreferenceDoesNotRestoreOldQueueEntries(): void
    {
        $users = new FakeReminderPreferenceUserRepository(enabled: false);
        $reminders = new FakeReminderPreferenceRepository();
        $service = new ReminderPreferenceService($users, $reminders);

        $enabled = $service->updateForTelegramUser(100001, true);

        $this->assertTrue($enabled);
        $this->assertTrue($users->enabled);
        $this->assertSame(['begin', 'settings:1', 'commit'], $reminders->events);
    }

    public function testRejectsUnknownUser(): void
    {
        $service = new ReminderPreferenceService(
            new FakeReminderPreferenceUserRepository(userExists: false),
            new FakeReminderPreferenceRepository()
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        $service->updateForTelegramUser(999999, false);
    }
}

class FakeReminderPreferenceUserRepository extends UserRepository
{
    public function __construct(
        public bool $enabled = true,
        private readonly bool $userExists = true
    ) {}

    public function findByTelegramId(int $telegramId): ?User
    {
        return $this->userExists
            ? new User($telegramId, 70, 175, 30, 'male', id: 5)
            : null;
    }

    public function findMealRemindersEnabledByTelegramId(int $telegramId): ?bool
    {
        return $this->userExists ? $this->enabled : null;
    }

    public function setMealRemindersEnabled(int $userId, bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}

class FakeReminderPreferenceRepository extends ReminderScheduleRepository
{
    /** @var list<string> */
    public array $events = [];

    public function __construct() {}

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

    public function setUserSettingsEnabled(int $userId, bool $enabled): void
    {
        $this->events[] = 'settings:' . (int)$enabled;
    }

    public function skipPendingForUser(int $userId): void
    {
        $this->events[] = 'skip-pending';
    }
}
