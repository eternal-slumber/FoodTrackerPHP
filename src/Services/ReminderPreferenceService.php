<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ReminderScheduleRepository;
use App\Repositories\UserRepository;

final class ReminderPreferenceService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ReminderScheduleRepository $reminders
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
}
