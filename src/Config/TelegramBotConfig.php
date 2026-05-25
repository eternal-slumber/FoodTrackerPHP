<?php

declare(strict_types=1);

namespace App\Config;

class TelegramBotConfig
{
    public function __construct(
        public readonly string $botToken,
        public readonly string $webhookSecretToken,
        public readonly string $miniAppUrl,
        public readonly int $defaultTimezoneOffsetMinutes
    ) {}

    public static function fromEnv(array $env): self
    {
        return new self(
            botToken: $env['TELEGRAM_BOT_TOKEN'] ?? '',
            webhookSecretToken: $env['TELEGRAM_WEBHOOK_SECRET_TOKEN'] ?? '',
            miniAppUrl: $env['TELEGRAM_MINI_APP_URL'] ?? '',
            defaultTimezoneOffsetMinutes: (int)($env['TELEGRAM_BOT_DEFAULT_TZ_OFFSET_MINUTES'] ?? 0)
        );
    }
}
