<?php

declare(strict_types=1);

namespace App\Services;

class AiQuotaService
{
    private const GENERAL_ACTION = 'ai_daily';
    private const GENERAL_LIMIT = 20;
    private const GENERAL_WINDOW_SECONDS = 86400;

    private const INSIGHT_BURST_ACTION = 'daily_insight_burst';
    private const INSIGHT_BURST_LIMIT = 1;
    private const INSIGHT_BURST_WINDOW_SECONDS = 5;

    private const INSIGHT_ACTION = 'daily_insight_hourly';
    private const INSIGHT_LIMIT = 12;
    private const INSIGHT_WINDOW_SECONDS = 3600;

    public function __construct(private readonly RateLimiterService $rateLimiter) {}

    public function consumeGeneral(int $telegramId): bool
    {
        return $this->rateLimiter->consume(
            $this->scope($telegramId),
            self::GENERAL_ACTION,
            self::GENERAL_LIMIT,
            self::GENERAL_WINDOW_SECONDS
        );
    }

    public function consumeInsightBurst(int $telegramId): bool
    {
        return $this->rateLimiter->consume(
            $this->scope($telegramId),
            self::INSIGHT_BURST_ACTION,
            self::INSIGHT_BURST_LIMIT,
            self::INSIGHT_BURST_WINDOW_SECONDS
        );
    }

    public function consumeInsight(int $telegramId): bool
    {
        return $this->rateLimiter->consume(
            $this->scope($telegramId),
            self::INSIGHT_ACTION,
            self::INSIGHT_LIMIT,
            self::INSIGHT_WINDOW_SECONDS
        );
    }

    /**
     * @return array{
     *     general:array{used:int, limit:int, remaining:int, resets_at:string, resets_in_seconds:int},
     *     insights:array{used:int, limit:int, remaining:int, resets_at:string, resets_in_seconds:int}
     * }
     */
    public function getUsageForTelegramUser(int $telegramId): array
    {
        $scope = $this->scope($telegramId);

        return [
            'general' => $this->rateLimiter->status(
                $scope,
                self::GENERAL_ACTION,
                self::GENERAL_LIMIT,
                self::GENERAL_WINDOW_SECONDS
            ),
            'insights' => $this->rateLimiter->status(
                $scope,
                self::INSIGHT_ACTION,
                self::INSIGHT_LIMIT,
                self::INSIGHT_WINDOW_SECONDS
            ),
        ];
    }

    private function scope(int $telegramId): string
    {
        return 'tg:' . $telegramId;
    }
}
