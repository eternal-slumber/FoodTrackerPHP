<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Config\TelegramBotConfig;
use App\Services\DailyNutritionSummaryService;
use App\Services\MealRecommendationService;
use App\Services\RateLimiterService;

class TelegramBotService
{
    public function __construct(
        private readonly TelegramBotConfig $config,
        private readonly TelegramBotClientInterface $client,
        private readonly TelegramBotMessageFactory $messages,
        private readonly DailyNutritionSummaryService $dailySummary,
        private readonly MealRecommendationService $mealRecommendation,
        private readonly RateLimiterService $rateLimiter
    ) {}

    public function isWebhookAuthorized(string $receivedSecret): bool
    {
        return $this->config->webhookSecretToken === ''
            || hash_equals($this->config->webhookSecretToken, $receivedSecret);
    }

    public function handleUpdate(array $update): void
    {
        $callbackQuery = $update['callback_query'] ?? null;
        if (is_array($callbackQuery)) {
            $this->handleCallbackQuery($callbackQuery);
            return;
        }

        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $telegramId = $message['from']['id'] ?? null;
        if (!is_int($chatId) && !is_string($chatId)) {
            return;
        }

        if (!is_int($telegramId) && !is_numeric($telegramId)) {
            return;
        }

        $text = trim((string)($message['text'] ?? ''));
        $command = $this->normalizeCommand($text);

        match ($command) {
            '/start' => $this->sendWelcome($chatId, $message),
            '/summary', TelegramBotMessageFactory::SUMMARY_BUTTON => $this->sendSummary($chatId, (int)$telegramId),
            '/eat', TelegramBotMessageFactory::RECOMMENDATION_BUTTON => $this->sendMealRecommendation($chatId, (int)$telegramId),
            '/help' => $this->client->sendMessage($chatId, $this->messages->help(), $this->messages->mainInlineKeyboard($this->config->miniAppUrl)),
            '/reminders', TelegramBotMessageFactory::REMINDERS_BUTTON => $this->sendReminders($chatId),
            default => $this->client->sendMessage($chatId, $this->messages->help(), $this->messages->mainInlineKeyboard($this->config->miniAppUrl)),
        };
    }

    private function sendWelcome(int|string $chatId, array $message): void
    {
        $firstName = isset($message['from']['first_name']) ? (string)$message['from']['first_name'] : null;

        $this->client->sendMessage(
            $chatId,
            $this->messages->welcome($firstName),
            $this->messages->mainInlineKeyboard($this->config->miniAppUrl)
        );
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackQueryId = isset($callbackQuery['id']) ? (string)$callbackQuery['id'] : '';
        if ($callbackQueryId !== '') {
            $this->client->answerCallbackQuery($callbackQueryId);
        }

        $message = $callbackQuery['message'] ?? null;
        $from = $callbackQuery['from'] ?? null;
        $chatId = is_array($message) ? ($message['chat']['id'] ?? null) : null;
        $telegramId = is_array($from) ? ($from['id'] ?? null) : null;

        if ((!is_int($chatId) && !is_string($chatId)) || (!is_int($telegramId) && !is_numeric($telegramId))) {
            return;
        }

        match ((string)($callbackQuery['data'] ?? '')) {
            TelegramBotMessageFactory::MAIN_MENU_CALLBACK => $this->sendWelcome($chatId, ['from' => $from]),
            TelegramBotMessageFactory::SUMMARY_CALLBACK => $this->sendSummary($chatId, (int)$telegramId),
            TelegramBotMessageFactory::RECOMMENDATION_CALLBACK => $this->sendMealRecommendation($chatId, (int)$telegramId),
            TelegramBotMessageFactory::REMINDERS_CALLBACK => $this->sendReminders($chatId),
            default => null,
        };
    }

    private function sendSummary(int|string $chatId, int $telegramId): void
    {
        $summary = $this->dailySummary->getForTelegramUser(
            $telegramId,
            $this->config->defaultTimezoneOffsetMinutes
        );

        if ($summary === null) {
            $this->client->sendMessage(
                $chatId,
                $this->messages->registrationRequired($this->config->miniAppUrl),
                $this->messages->miniAppInlineKeyboard($this->config->miniAppUrl)
            );
            return;
        }

        $this->client->sendMessage(
            $chatId,
            $this->messages->summary($summary),
            $this->messages->summaryInlineKeyboard($this->config->miniAppUrl)
        );
    }

    private function sendMealRecommendation(int|string $chatId, int $telegramId): void
    {
        if (!$this->rateLimiter->consume('tg:' . $telegramId, 'ai_daily', 20, 86400)) {
            $this->client->sendMessage(
                $chatId,
                'Дневной лимит AI-запросов исчерпан. Попробуй позже.',
                $this->messages->recommendationInlineKeyboard($this->config->miniAppUrl)
            );
            return;
        }

        $thinkingMessage = $this->client->sendMessage($chatId, 'Думаю...');
        $thinkingMessageId = isset($thinkingMessage['message_id']) ? (int)$thinkingMessage['message_id'] : 0;

        $recommendation = $this->mealRecommendation->recommendForTelegramUser(
            $telegramId,
            $this->config->defaultTimezoneOffsetMinutes
        );

        if ($recommendation === null) {
            $this->client->sendMessage(
                $chatId,
                $this->messages->registrationRequired($this->config->miniAppUrl),
                $this->messages->miniAppInlineKeyboard($this->config->miniAppUrl)
            );
            return;
        }

        $this->sendOrEditMessage(
            $chatId,
            $thinkingMessageId,
            $recommendation,
            $this->messages->recommendationInlineKeyboard($this->config->miniAppUrl)
        );
    }

    private function sendReminders(int|string $chatId): void
    {
        $this->client->sendMessage(
            $chatId,
            $this->messages->reminders($this->config->miniAppUrl),
            $this->messages->miniAppInlineKeyboard($this->config->miniAppUrl)
        );
    }

    private function sendOrEditMessage(
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null
    ): void {
        if ($messageId < 1) {
            $this->client->sendMessage($chatId, $text, $replyMarkup);
            return;
        }

        try {
            $this->client->editMessageText($chatId, $messageId, $text, $replyMarkup);
        } catch (\Throwable $e) {
            error_log('Telegram bot edit message error: ' . $e->getMessage());
            $this->client->sendMessage($chatId, $text, $replyMarkup);
        }
    }

    private function normalizeCommand(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if ($text === TelegramBotMessageFactory::SUMMARY_BUTTON || $text === TelegramBotMessageFactory::REMINDERS_BUTTON) {
            return $text;
        }

        $normalized = strtolower($text);
        if (!str_starts_with($normalized, '/')) {
            return $text;
        }

        $command = strtok($normalized, " \n\t") ?: $normalized;
        $atPosition = strpos($command, '@');

        return $atPosition === false ? $command : substr($command, 0, $atPosition);
    }
}
