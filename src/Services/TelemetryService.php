<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class TelemetryService
{
    public function __construct(private readonly PDO $db) {}

    public function recordUserEvent(
        ?int $userId,
        string $eventName,
        ?array $eventData = null,
        ?Request $request = null
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO user_events (user_id, event_name, event_data, ip_address, user_agent)
                 VALUES (:user_id, :event_name, :event_data, :ip_address, :user_agent)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'event_name' => substr($eventName, 0, 80),
                'event_data' => $this->encodeJson($eventData),
                'ip_address' => $request ? $this->clientIp($request) : null,
                'user_agent' => $request ? substr($request->getHeaderLine('User-Agent'), 0, 500) : null,
            ]);
        } catch (Throwable $e) {
            error_log('Telemetry user event write failed: ' . $e->getMessage());
        }
    }

    public function recordAiRequest(
        string $requestType,
        string $status,
        ?int $responseTimeMs = null,
        ?string $errorMessage = null,
        ?int $userId = null,
        ?int $aiModelId = null
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ai_requests (user_id, ai_model_id, request_type, status, response_time_ms, error_message)
                 VALUES (:user_id, :ai_model_id, :request_type, :status, :response_time_ms, :error_message)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'ai_model_id' => $aiModelId,
                'request_type' => substr($requestType, 0, 60),
                'status' => substr($status, 0, 30),
                'response_time_ms' => $responseTimeMs,
                'error_message' => $errorMessage !== null ? substr($errorMessage, 0, 2000) : null,
            ]);
        } catch (Throwable $e) {
            error_log('Telemetry AI request write failed: ' . $e->getMessage());
        }
    }

    public function recordSystemError(
        string $level,
        string $channel,
        string $message,
        array $context = [],
        ?Throwable $exception = null
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO system_logs (level, channel, message, context, exception_class, trace_id)
                 VALUES (:level, :channel, :message, :context, :exception_class, :trace_id)'
            );
            $stmt->execute([
                'level' => substr($level, 0, 20),
                'channel' => substr($channel, 0, 60),
                'message' => substr($message, 0, 4000),
                'context' => $this->encodeJson($context),
                'exception_class' => $exception ? substr($exception::class, 0, 255) : null,
                'trace_id' => $this->traceId($context),
            ]);
        } catch (Throwable $e) {
            error_log('Telemetry system log write failed: ' . $e->getMessage());
        }
    }

    private function encodeJson(?array $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return json_encode(['serialization_error' => true], JSON_THROW_ON_ERROR);
        }
    }

    private function clientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        return substr((string)($serverParams['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function traceId(array $context): ?string
    {
        $traceId = $context['trace_id'] ?? null;

        return is_scalar($traceId) ? substr((string)$traceId, 0, 80) : null;
    }
}
