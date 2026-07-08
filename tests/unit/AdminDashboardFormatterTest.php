<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\View\AdminDashboardFormatter;
use PHPUnit\Framework\TestCase;

class AdminDashboardFormatterTest extends TestCase
{
    public function testRendersUtcActivityTimeAsMoscowTime(): void
    {
        $formatter = new AdminDashboardFormatter();

        $html = $formatter->renderUserActivityRows([[
            'id' => 1,
            'user_id' => 10,
            'tg_id' => '1614055192',
            'event_name' => 'app_opened',
            'event_data' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => '2026-06-17 10:05:00',
        ]]);

        $this->assertStringContainsString('17.06.2026 13:05', $html);
    }

    public function testEscapesSystemLogMessage(): void
    {
        $formatter = new AdminDashboardFormatter();

        $html = $formatter->renderSystemLogRows([[
            'id' => 1,
            'level' => 'error',
            'channel' => 'http',
            'message' => '<script>alert(1)</script>',
            'exception_class' => 'RuntimeException',
            'trace_id' => 'trace-1',
            'created_at' => '2026-06-30 10:00:00',
        ]]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testExplainsRecommendationAndUnknownAiRequestTypes(): void
    {
        $formatter = new AdminDashboardFormatter();
        $requests = [
            [
                'id' => 1,
                'user_id' => 1,
                'request_type' => 'dailyNutritionInsight',
                'status' => 'success',
                'response_time_ms' => 400,
                'error_message' => null,
                'created_at' => '2026-06-30 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'request_type' => 'customFutureOperation',
                'status' => 'success',
                'response_time_ms' => 300,
                'error_message' => null,
                'created_at' => '2026-06-30 10:05:00',
            ],
        ];

        $html = $formatter->renderAiRequestRows($requests);

        $this->assertStringContainsString('Рекомендация по рациону', $html);
        $this->assertStringContainsString('Другая операция: customFutureOperation', $html);
    }
}
