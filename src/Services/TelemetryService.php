<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class TelemetryService
{
    private const AI_ERROR_CATEGORIES = [
        'request_encoding_failed',
        'transport_error',
        'authentication_error',
        'rate_limit',
        'retryable_error',
        'configuration_error',
        'invalid_json_response',
        'missing_content',
    ];

    public function __construct(private readonly PDO $db) {}

    /** @param array<string, mixed>|null $eventData */
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
        ?string $errorCategory = null,
        ?int $userId = null,
        ?int $aiModelId = null,
        ?string $provider = null,
        ?string $model = null,
        ?int $httpStatus = null,
        ?string $traceId = null
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ai_requests
                    (user_id, ai_model_id, request_type, status, response_time_ms, error_message,
                     provider, model, http_status, trace_id)
                 VALUES
                    (:user_id, :ai_model_id, :request_type, :status, :response_time_ms, :error_message,
                     :provider, :model, :http_status, :trace_id)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'ai_model_id' => $aiModelId,
                'request_type' => substr($requestType, 0, 60),
                'status' => substr($status, 0, 30),
                'response_time_ms' => $responseTimeMs,
                'error_message' => $this->safeAiErrorCategory($errorCategory),
                'provider' => $provider !== null ? substr($provider, 0, 60) : null,
                'model' => $model !== null ? substr($model, 0, 180) : null,
                'http_status' => $httpStatus !== null && $httpStatus >= 100 && $httpStatus <= 599
                    ? $httpStatus
                    : null,
                'trace_id' => $traceId !== null ? substr($traceId, 0, 80) : null,
            ]);
        } catch (Throwable $e) {
            error_log('Telemetry AI request write failed: ' . $e->getMessage());
        }
    }

    private function safeAiErrorCategory(?string $errorCategory): ?string
    {
        if ($errorCategory === null) {
            return null;
        }

        return in_array($errorCategory, self::AI_ERROR_CATEGORIES, true)
            ? $errorCategory
            : 'unspecified_error';
    }

    /** @param array<string, mixed> $context */
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

    /** @param array<string, mixed>|null $value */
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
        $remoteAddress = $serverParams['REMOTE_ADDR'] ?? '';

        return is_scalar($remoteAddress) ? substr((string)$remoteAddress, 0, 45) : '';
    }

    /** @param array<string, mixed> $context */
    private function traceId(array $context): ?string
    {
        $traceId = $context['trace_id'] ?? null;

        return is_scalar($traceId) ? substr((string)$traceId, 0, 80) : null;
    }
}
