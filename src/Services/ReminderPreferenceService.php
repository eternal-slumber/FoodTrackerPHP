<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;

final class ReminderPreferenceService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ReminderScheduleRepository $reminders,
        private readonly EveningSummaryScheduleService $eveningSummaries
    ) {}

    public function getForTelegramUser(int $telegramId): ?bool
    {
        return $this->users->findMealRemindersEnabledByTelegramId($telegramId);
    }

    public function updateForTelegramUser(int $telegramId, bool $enabled): bool
    {
        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $this->reminders->beginTransaction();

        try {
            $this->users->setMealRemindersEnabled($user->id, $enabled);
            $this->reminders->setUserSettingsEnabled($user->id, $enabled);

            if (!$enabled) {
                $this->reminders->skipPendingForUser($user->id);
            }

            $this->reminders->commit();
        } catch (\Throwable $error) {
            $this->reminders->rollBack();
            throw $error;
        }

        return $enabled;
    }

    /** @return array{enabled:bool,time:string}|null */
    public function getEveningSummaryForTelegramUser(int $telegramId): ?array
    {
        return $this->users->findEveningSummarySettingsByTelegramId($telegramId);
    }

    /** @return array{enabled:bool,time:string} */
    public function updateEveningSummaryForTelegramUser(int $telegramId, bool $enabled, string $time): array
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new \InvalidArgumentException('Invalid evening summary time');
        }

        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $this->reminders->beginTransaction();

        try {
            $this->users->setEveningSummarySettings($user->id, $enabled, $time);

            if ($enabled) {
                $this->eveningSummaries->reschedulePendingForUser($user->id, $time);
            } else {
                $this->reminders->skipPendingEveningSummariesForUser($user->id);
            }

            $this->reminders->commit();
        } catch (\Throwable $error) {
            $this->reminders->rollBack();
            throw $error;
        }

        return ['enabled' => $enabled, 'time' => $time];
    }
}
