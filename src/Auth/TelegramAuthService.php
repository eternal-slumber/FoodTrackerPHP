<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\ValidationException;

class TelegramAuthService
{
    public function __construct(
        private readonly string $botToken,
        private readonly int $maxAgeSeconds = 86400
    ) {}

    public function validateInitData(string $initData): CurrentUser
    {
        if ($this->botToken === '') {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        parse_str($initData, $data);

        if (empty($data['hash']) || !is_string($data['hash'])) {
            throw new ValidationException('Missing Telegram auth hash');
        }

        $receivedHash = $data['hash'];
        unset($data['hash']);

        ksort($data, SORT_STRING);
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        $checkString = implode("\n", $pairs);
        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($calculatedHash, $receivedHash)) {
            throw new ValidationException('Invalid Telegram auth signature');
        }

        $authDate = isset($data['auth_date']) ? (int)$data['auth_date'] : 0;
        if ($authDate < 1 || time() - $authDate > $this->maxAgeSeconds) {
            throw new ValidationException('Telegram auth data expired');
        }

        $userData = json_decode((string)($data['user'] ?? ''), true);
        if (!is_array($userData) || empty($userData['id'])) {
            throw new ValidationException('Missing Telegram user data');
        }

        return new CurrentUser(
            telegramId: (int)$userData['id'],
            username: isset($userData['username']) ? (string)$userData['username'] : null,
            firstName: isset($userData['first_name']) ? (string)$userData['first_name'] : null,
            lastName: isset($userData['last_name']) ? (string)$userData['last_name'] : null
        );
    }
}
