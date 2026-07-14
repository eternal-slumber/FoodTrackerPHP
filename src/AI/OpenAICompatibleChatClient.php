<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\AIAuthenticationException;
use App\AI\Exceptions\AIConfigurationException;
use App\AI\Exceptions\AIInvalidResponseException;
use App\AI\Exceptions\AIRateLimitException;
use App\AI\Exceptions\AIRetryableException;
use App\Config\AIProviderConfig;
use App\Services\TelemetryService;
use JsonException;

class OpenAICompatibleChatClient implements AIChatClientInterface
{
    public function __construct(
        private readonly AIProviderConfig $config,
        private readonly ?TelemetryService $telemetry = null
    ) {}


    /**
     * @param list<array{
     *     role: string,
     *     content: string|list<array<string, mixed>>
     * }> $messages
     * @param array{
     *     model_purpose?: string,
     *     max_tokens?: int,
     *     temperature?: float,
     *     json_schema?: array<string, mixed>
     * } $options
     */
    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): string {
        $startedAt = microtime(true);
        $traceId = bin2hex(random_bytes(16));
        $modelPurpose = (string)($options['model_purpose'] ?? 'text');
        $model = $this->config->modelForPurpose($modelPurpose);
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = max(1, min(4096, (int)$options['max_tokens']));
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = max(0, min(2, (float)$options['temperature']));
        }

        if (is_array($options['json_schema'] ?? null)) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $options['json_schema'],
            ];
        }

        $url = $this->config->baseUrl . '/chat/completions';
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_POST, true);
        try {
            $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->logFailure($operation, $model, $traceId, 'request_encoding_failed');
            $this->recordTelemetry(
                $operation,
                'error',
                $startedAt,
                $model,
                $traceId,
                null,
                'request_encoding_failed'
            );

            throw new AIConfigurationException(
                'Не удалось подготовить запрос к AI-сервису',
                500,
                [],
                $exception
            );
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->apiKey,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorCode = curl_errno($ch);

        if ($response === false) {
            $this->logFailure($operation, $model, $traceId, 'transport_error', null, $curlErrorCode);
            $this->recordTelemetry(
                $operation,
                'error',
                $startedAt,
                $model,
                $traceId,
                null,
                'transport_error'
            );

            throw new AIRetryableException('AI-сервис временно недоступен', 503);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorCategory = $this->httpErrorCategory($httpCode);
            $this->logFailure($operation, $model, $traceId, $errorCategory, $httpCode);
            $this->recordTelemetry(
                $operation,
                'error',
                $startedAt,
                $model,
                $traceId,
                $httpCode,
                $errorCategory
            );

            $this->throwForHttpStatus($httpCode);
        }

        $result = json_decode((string)$response, true);
        if (!is_array($result)) {
            $this->logFailure($operation, $model, $traceId, 'invalid_json_response', $httpCode);
            $this->recordTelemetry(
                $operation,
                'error',
                $startedAt,
                $model,
                $traceId,
                $httpCode,
                'invalid_json_response'
            );

            throw new AIInvalidResponseException('AI-сервис вернул некорректный ответ', 502);
        }

        $choices = $result['choices'] ?? null;
        $choice = is_array($choices) ? ($choices[0] ?? null) : null;
        $message = is_array($choice) ? ($choice['message'] ?? null) : null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;

        if (!is_string($content)) {
            $this->logFailure($operation, $model, $traceId, 'missing_content', $httpCode);
            $this->recordTelemetry(
                $operation,
                'error',
                $startedAt,
                $model,
                $traceId,
                $httpCode,
                'missing_content'
            );

            throw new AIInvalidResponseException('AI-сервис вернул ответ без содержимого', 502);
        }

        $this->recordTelemetry($operation, 'success', $startedAt, $model, $traceId, $httpCode);

        return $content;
    }

    private function throwForHttpStatus(int $httpCode): never
    {
        if ($httpCode === 401 || $httpCode === 403) {
            throw new AIAuthenticationException('Ошибка авторизации AI-сервиса', 503);
        }

        if ($httpCode === 429) {
            throw new AIRateLimitException('AI-сервис временно исчерпал лимит запросов', 429);
        }

        if ($httpCode === 0 || $httpCode === 408 || $httpCode === 425 || $httpCode >= 500) {
            throw new AIRetryableException('AI-сервис временно недоступен', 503);
        }

        throw new AIConfigurationException('AI-сервис отклонил запрос', 502);
    }

    private function httpErrorCategory(int $httpCode): string
    {
        if ($httpCode === 401 || $httpCode === 403) {
            return 'authentication_error';
        }

        if ($httpCode === 429) {
            return 'rate_limit';
        }

        if ($httpCode === 0 || $httpCode === 408 || $httpCode === 425 || $httpCode >= 500) {
            return 'retryable_error';
        }

        return 'configuration_error';
    }

    private function logFailure(
        string $operation,
        string $model,
        string $traceId,
        string $errorCategory,
        ?int $httpCode = null,
        ?int $curlErrorCode = null
    ): void {
        error_log(sprintf(
            'AI provider [%s] model [%s] operation [%s] trace [%s] error [%s] http [%s] curl [%s]',
            $this->safeLogValue($this->config->provider, 60),
            $this->safeLogValue($model, 180),
            $this->safeLogValue($operation, 60),
            $traceId,
            $errorCategory,
            $httpCode !== null ? (string)$httpCode : '-',
            $curlErrorCode !== null ? (string)$curlErrorCode : '-'
        ));
    }

    private function safeLogValue(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';

        return substr($value, 0, $maxLength);
    }

    private function recordTelemetry(
        string $operation,
        string $status,
        float $startedAt,
        string $model,
        string $traceId,
        ?int $httpStatus = null,
        ?string $errorCategory = null
    ): void {
        $this->telemetry?->recordAiRequest(
            $operation,
            $status,
            max(0, (int)round((microtime(true) - $startedAt) * 1000)),
            $errorCategory,
            provider: $this->config->provider,
            model: $model,
            httpStatus: $httpStatus,
            traceId: $traceId
        );
    }
}
