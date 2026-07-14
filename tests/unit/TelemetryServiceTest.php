<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TelemetryService;
use PDO;
use PHPUnit\Framework\TestCase;

class TelemetryServiceTest extends TestCase
{
    private PDO $db;
    private TelemetryService $telemetry;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE ai_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                ai_model_id INTEGER NULL,
                request_type TEXT NOT NULL,
                status TEXT NOT NULL,
                response_time_ms INTEGER NULL,
                error_message TEXT NULL,
                provider TEXT NULL,
                model TEXT NULL,
                http_status INTEGER NULL,
                trace_id TEXT NULL
            )'
        );
        $this->telemetry = new TelemetryService($this->db);
    }

    public function testStoresSafeAiDiagnostics(): void
    {
        $this->telemetry->recordAiRequest(
            'analyze',
            'error',
            125,
            'rate_limit',
            provider: 'openrouter',
            model: 'test/model',
            httpStatus: 429,
            traceId: 'trace-123'
        );

        $row = $this->db->query('SELECT * FROM ai_requests')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('analyze', $row['request_type']);
        $this->assertSame('error', $row['status']);
        $this->assertSame(125, (int)$row['response_time_ms']);
        $this->assertSame('rate_limit', $row['error_message']);
        $this->assertSame('openrouter', $row['provider']);
        $this->assertSame('test/model', $row['model']);
        $this->assertSame(429, (int)$row['http_status']);
        $this->assertSame('trace-123', $row['trace_id']);
    }

    public function testDoesNotStoreArbitraryAiErrorText(): void
    {
        $this->telemetry->recordAiRequest(
            'analyze',
            'error',
            errorCategory: 'prompt and full provider response'
        );

        $errorMessage = $this->db->query('SELECT error_message FROM ai_requests')->fetchColumn();

        $this->assertSame('unspecified_error', $errorMessage);
    }
}
