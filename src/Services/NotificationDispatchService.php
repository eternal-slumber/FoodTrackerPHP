<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\TelegramBotConfig;
use App\Repositories\MealRepository;
use App\Repositories\ReminderScheduleRepository;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use DateTimeImmutable;
use DateTimeZone;

final class NotificationDispatchService
{
    private const NOTIFICATION_TYPE = 'meal_reminder';
    private const PROCESSING_TIMEOUT_MINUTES = 15;

    private const MEAL_DESCRIPTION_PREFIXES = [
        'breakfast' => 'Завтрак',
        'lunch' => 'Обед',
        'dinner' => 'Ужин',
    ];

    public function __construct(
        private readonly ReminderScheduleRepository $reminders,
        private readonly ReminderScheduleService $schedule,
        private readonly MealRepository $meals,
        private readonly TelegramBotClientInterface $telegram,
        private readonly TelegramBotMessageFactory $messages,
        private readonly TelegramBotConfig $telegramConfig
    ) {}

    /** @return array{claimed:int, sent:int, skipped:int, failed:int, next_scheduled:int} */
    public function dispatchDue(?DateTimeImmutable $nowUtc = null, int $limit = 50): array
    {
        $now = ($nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $notifications = $this->reminders->claimDueNotifications(
            $now->format('Y-m-d H:i:s'),
            $now->modify('-' . self::PROCESSING_TIMEOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s'),
            $limit
        );
        $result = [
            'claimed' => count($notifications),
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'next_scheduled' => 0,
        ];

        foreach ($notifications as $notification) {
            $notificationId = (int)($notification['id'] ?? 0);
            $canScheduleNext = $this->canContinueSchedule($notification);

            try {
                if (!$canScheduleNext) {
                    $this->reminders->markSkipped($notificationId);
                    $result['skipped']++;
                    continue;
                }

                if ($this->mealAlreadyAdded($notification)) {
                    $this->reminders->markSkipped($notificationId);
                    $result['skipped']++;
                } else {
                    $this->telegram->sendMessage(
                        (string)$notification['telegram_id'],
                        $this->messages->mealReminder((string)$notification['meal_type']),
                        $this->messages->miniAppInlineKeyboard($this->telegramConfig->miniAppUrl)
                    );
                    $this->reminders->markSent($notificationId, $now->format('Y-m-d H:i:s'));
                    $result['sent']++;
                }
            } catch (\Throwable $error) {
                $this->markFailedSafely($notificationId, $error);
                $result['failed']++;
            }

            if ($canScheduleNext && $this->scheduleNextSafely($notification)) {
                $result['next_scheduled']++;
            }
        }

        return $result;
    }

    private function canContinueSchedule(array $notification): bool
    {
        $mealType = (string)($notification['meal_type'] ?? '');

        return ($notification['notification_type'] ?? null) === self::NOTIFICATION_TYPE
            && isset(self::MEAL_DESCRIPTION_PREFIXES[$mealType])
            && (int)($notification['setting_enabled'] ?? 0) === 1
            && trim((string)($notification['reminder_time'] ?? '')) !== '';
    }

    private function mealAlreadyAdded(array $notification): bool
    {
        $mealType = (string)$notification['meal_type'];

        return $this->meals->existsForLocalDateWithDescriptionPrefix(
            (int)$notification['user_id'],
            (string)$notification['local_date'],
            self::MEAL_DESCRIPTION_PREFIXES[$mealType],
            (int)$notification['timezone_offset']
        );
    }

    private function scheduleNextSafely(array $notification): bool
    {
        try {
            $this->schedule->scheduleNextFromSetting(
                (int)$notification['user_id'],
                (string)$notification['meal_type'],
                (string)$notification['reminder_time'],
                (int)$notification['remind_before_minutes'],
                (int)$notification['timezone_offset'],
                (string)$notification['local_date']
            );

            return true;
        } catch (\Throwable $error) {
            error_log(sprintf(
                'Failed to schedule next reminder for notification %d: %s',
                (int)$notification['id'],
                $error->getMessage()
            ));

            return false;
        }
    }

    private function markFailedSafely(int $notificationId, \Throwable $error): void
    {
        try {
            $this->reminders->markFailed($notificationId, $error->getMessage());
        } catch (\Throwable $markError) {
            error_log(sprintf(
                'Failed to mark notification %d as failed: %s',
                $notificationId,
                $markError->getMessage()
            ));
        }

        error_log(sprintf(
            'Notification %d dispatch failed: %s',
            $notificationId,
            $error->getMessage()
        ));
    }
}
