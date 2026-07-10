<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\TelegramBotConfig;
use App\Repositories\MealRepository;
use App\Telegram\TelegramBotClientInterface;
use App\Telegram\TelegramBotMessageFactory;
use DateTimeImmutable;

class EveningSummaryDeliveryService
{
    public function __construct(
        private readonly MealRepository $meals,
        private readonly DailyNutritionSummaryService $dailySummary,
        private readonly DailyNutritionInsightService $dailyInsight,
        private readonly TelegramBotClientInterface $telegram,
        private readonly TelegramBotMessageFactory $messages,
        private readonly TelegramBotConfig $telegramConfig
    ) {}

    public function send(array $notification, DateTimeImmutable $nowUtc): bool
    {
        $userId = (int)$notification['user_id'];
        $telegramId = (int)$notification['telegram_id'];
        $timezoneOffset = $this->timezoneOffset($notification);
        $localDate = (string)$notification['local_date'];
        $todayLocal = $nowUtc
            ->modify(sprintf('%+d minutes', -$timezoneOffset))
            ->format('Y-m-d');

        if ($localDate !== $todayLocal
            || !$this->meals->existsForLocalDate($userId, $localDate, $timezoneOffset)
        ) {
            return false;
        }

        $summary = $this->dailySummary->getForTelegramUser($telegramId, $timezoneOffset, $nowUtc);
        if ($summary === null) {
            return false;
        }

        $shortInsight = null;
        try {
            $insight = $this->dailyInsight->refreshForTelegramUser($telegramId, $timezoneOffset, $nowUtc);
            $shortInsight = $insight['insight']['short_summary'] ?? null;
        } catch (\Throwable $error) {
            error_log('Evening summary AI refresh failed: ' . $error->getMessage());
        }

        $this->telegram->sendMessage(
            $telegramId,
            $this->messages->eveningSummary($summary, is_string($shortInsight) ? $shortInsight : null),
            $this->messages->eveningSummaryInlineKeyboard($this->telegramConfig->miniAppUrl)
        );

        return true;
    }

    private function timezoneOffset(array $notification): int
    {
        $payload = json_decode((string)($notification['payload'] ?? ''), true);
        $offset = (int)($payload['timezone_offset'] ?? 0);

        return max(-840, min(840, $offset));
    }
}
