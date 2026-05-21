<?php

declare(strict_types=1);

namespace App\Config;

class TelegramAuthConfig
{
    public function __construct(
        public readonly string $botToken,
        public readonly int $maxAgeSeconds,
        public readonly string $appEnv,
        public readonly bool $devAuthEnabled,
        public readonly int $devUserId,
        public readonly string $devUsername
    ) {}

    public static function fromEnv(array $env): self
    {
        return new self(
            botToken: $env['TELEGRAM_BOT_TOKEN'] ?? '',
            maxAgeSeconds: (int)($env['TELEGRAM_AUTH_MAX_AGE_SECONDS'] ?? 86400),
            appEnv: $env['APP_ENV'] ?? 'production',
            devAuthEnabled: filter_var($env['TELEGRAM_DEV_AUTH_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            devUserId: (int)($env['TELEGRAM_DEV_USER_ID'] ?? 100001),
            devUsername: $env['TELEGRAM_DEV_USERNAME'] ?? 'dev_user'
        );
    }
}
