<?php

declare(strict_types=1);

namespace App\AI;

use App\Config\AIProviderConfig;
use App\Services\TelemetryService;

class OpenAICompatibleChatClient implements AIChatClientInterface
{
    public function __construct(
        private readonly AIProviderConfig $config,
        private readonly ?TelemetryService $telemetry = null
    ) {}

    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): ?string {
        $startedAt = microtime(true);
        $modelPurpose = (string)($options['model_purpose'] ?? 'text');
        $payload = [
            'model' => $this->config->modelForPurpose($modelPurpose),
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->apiKey,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            error_log($this->logPrefix() . " {$operation} cURL error: {$curlError}");
            $this->recordTelemetry($operation, 'error', $startedAt, "cURL error: {$curlError}");

            return null;
        }

        if ($httpCode !== 200) {
            error_log($this->logPrefix() . " {$operation} HTTP {$httpCode}. Response: {$response}. URL: {$url}");
            $this->recordTelemetry($operation, 'error', $startedAt, "HTTP {$httpCode}");

            return null;
        }

        $result = json_decode((string)$response, true);
        if (!is_array($result)) {
            error_log($this->logPrefix() . " {$operation} JSON error: " . json_last_error_msg());
            $this->recordTelemetry($operation, 'error', $startedAt, 'JSON error: ' . json_last_error_msg());

            return null;
        }

        $content = $result['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            $this->recordTelemetry($operation, 'error', $startedAt, 'Missing content in AI response');

            return null;
        }

        $this->recordTelemetry($operation, 'success', $startedAt);

        return $content;
    }

    private function logPrefix(): string
    {
        return 'AI provider [' . $this->config->provider . ']';
    }

    private function recordTelemetry(
        string $operation,
        string $status,
        float $startedAt,
        ?string $errorMessage = null
    ): void {
        $this->telemetry?->recordAiRequest(
            $operation,
            $status,
            max(0, (int)round((microtime(true) - $startedAt) * 1000)),
            $errorMessage
        );
    }
}
