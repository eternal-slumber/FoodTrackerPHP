<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Config\TelegramBotConfig;

class TelegramBotApiClient implements TelegramBotClientInterface
{
    public function __construct(private readonly TelegramBotConfig $config) {}

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        if ($this->config->botToken === '') {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $result = $this->post('sendMessage', $payload);

        return is_array($result) ? $result : null;
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->post('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->post('editMessageText', $payload);
    }

    private function post(string $method, array $payload): mixed
    {
        $ch = curl_init(sprintf('https://api.telegram.org/bot%s/%s', $this->config->botToken, $method));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        if ($response === false) {
            throw new \RuntimeException('Telegram Bot API cURL error: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Telegram Bot API HTTP {$httpCode}: {$response}");
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            throw new \RuntimeException("Telegram Bot API invalid response: {$response}");
        }

        return $decoded['result'] ?? null;
    }
}
