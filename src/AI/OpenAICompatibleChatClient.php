<?php

declare(strict_types=1);

namespace App\AI;

use App\Config\AIProviderConfig;

class OpenAICompatibleChatClient implements AIChatClientInterface
{
    public function __construct(private readonly AIProviderConfig $config) {}

    public function complete(array $messages, int $timeoutSeconds, string $operation): ?string
    {
        $payload = [
            'model' => $this->config->model,
            'messages' => $messages,
        ];

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
        curl_close($ch);

        if ($response === false) {
            error_log($this->logPrefix() . " {$operation} cURL error: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log($this->logPrefix() . " {$operation} HTTP {$httpCode}. Response: {$response}. URL: {$url}");
            return null;
        }

        $result = json_decode((string)$response, true);
        if (!is_array($result)) {
            error_log($this->logPrefix() . " {$operation} JSON error: " . json_last_error_msg());
            return null;
        }

        $content = $result['choices'][0]['message']['content'] ?? null;

        return is_string($content) ? $content : null;
    }

    private function logPrefix(): string
    {
        return 'AI provider [' . $this->config->provider . ']';
    }
}
