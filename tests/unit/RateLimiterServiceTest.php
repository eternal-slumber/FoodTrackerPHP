<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RateLimiterService;
use PDO;
use PHPUnit\Framework\TestCase;

class RateLimiterServiceTest extends TestCase
{
    public function testReadsCurrentWindowWithoutConsumingAttempt(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec(
            'CREATE TABLE rate_limits (
                scope_key TEXT NOT NULL,
                action TEXT NOT NULL,
                window_start TEXT NOT NULL,
                attempts INTEGER NOT NULL
            )'
        );

        $now = 1_700_000_123;
        $windowSeconds = 3600;
        $windowStart = $now - ($now % $windowSeconds);
        $stmt = $db->prepare(
            'INSERT INTO rate_limits (scope_key, action, window_start, attempts) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(['tg:100001', 'daily_insight_hourly', date('Y-m-d H:i:s', $windowStart), 3]);

        $status = (new RateLimiterService($db))->status(
            'tg:100001',
            'daily_insight_hourly',
            12,
            $windowSeconds,
            $now
        );

        $this->assertSame(3, $status['used']);
        $this->assertSame(9, $status['remaining']);
        $this->assertSame(12, $status['limit']);
        $this->assertSame($windowStart + $windowSeconds - $now, $status['resets_in_seconds']);
        $this->assertSame(3, (int)$db->query('SELECT attempts FROM rate_limits')->fetchColumn());
    }
}
