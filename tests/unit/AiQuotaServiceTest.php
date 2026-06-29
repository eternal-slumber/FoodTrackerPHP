<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiQuotaService;
use App\Services\RateLimiterService;
use PHPUnit\Framework\TestCase;

class AiQuotaServiceTest extends TestCase
{
    public function testUsesOnePolicyForConsumptionAndUsageDisplay(): void
    {
        $rateLimiter = new FakeAiQuotaRateLimiter();
        $service = new AiQuotaService($rateLimiter);

        $this->assertTrue($service->consumeGeneral(100001));
        $this->assertTrue($service->consumeInsightBurst(100001));
        $this->assertTrue($service->consumeInsight(100001));

        $usage = $service->getUsageForTelegramUser(100001);

        $this->assertSame(20, $usage['general']['limit']);
        $this->assertSame(14, $usage['general']['remaining']);
        $this->assertSame(12, $usage['insights']['limit']);
        $this->assertSame(9, $usage['insights']['remaining']);
        $this->assertSame([
            ['tg:100001', 'ai_daily', 20, 86400],
            ['tg:100001', 'daily_insight_burst', 1, 5],
            ['tg:100001', 'daily_insight_hourly', 12, 3600],
        ], $rateLimiter->consumeCalls);
    }
}

class FakeAiQuotaRateLimiter extends RateLimiterService
{
    public array $consumeCalls = [];

    public function __construct() {}

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        $this->consumeCalls[] = [$scope, $action, $limit, $windowSeconds];

        return true;
    }

    public function status(
        string $scope,
        string $action,
        int $limit,
        int $windowSeconds,
        ?int $now = null
    ): array {
        $used = $action === 'ai_daily' ? 6 : 3;

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit - $used,
            'resets_at' => '2026-06-29T00:00:00+00:00',
            'resets_in_seconds' => $windowSeconds,
        ];
    }
}
