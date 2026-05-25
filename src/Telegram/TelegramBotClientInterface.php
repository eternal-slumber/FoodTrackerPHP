<?php

declare(strict_types=1);

namespace App\Telegram;

interface TelegramBotClientInterface
{
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): ?array;

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void;

    public function answerCallbackQuery(string $callbackQueryId): void;
}
