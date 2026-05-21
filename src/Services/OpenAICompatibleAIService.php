<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\AIProviderConfig;
use App\Interfaces\AIServiceInterface;

class OpenAICompatibleAIService implements AIServiceInterface
{
    public function __construct(private readonly AIProviderConfig $config) {}

    public function analyze(string $imagePath): array
    {
        $imageData = base64_encode((string)file_get_contents($imagePath));
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($imagePath) ?: 'image/jpeg';

        $prompt = <<<'PROMPT'
Ты — помощник по оценке питания по фото. Твоя задача — дать осторожную, редактируемую оценку для черновика приема пищи.

Правила:
- Верни ТОЛЬКО валидный JSON без markdown, комментариев и пояснений.
- Название блюда в поле "food" пиши ТОЛЬКО на русском языке.
- Не выдумывай конкретное блюдо, если по фото оно не распознается уверенно.
- Если еда не видна, фото не еды или качество не позволяет оценить состав: верни нули и "food": "не определено".
- Если видна еда, но состав/вес неясны, используй обобщенное русское название, например "салат", "каша", "мясо с гарниром", и понижай confidence.
- Не указывай бренд, редкие ингредиенты или способ приготовления, если они явно не видны.
- kcal, proteins, fats и carbs оценивай для всей видимой порции, а не на 100 г.
- Значения должны быть числами: kcal целое, proteins/fats/carbs можно с 1 знаком после запятой.
- confidence — число от 0 до 1.

Формат ответа:
{"food":"название на русском","kcal":123,"proteins":12.3,"fats":4.5,"carbs":30.1,"confidence":0.72}

Если оценка невозможна:
{"food":"не определено","kcal":0,"proteins":0,"fats":0,"carbs":0,"confidence":0}
PROMPT;

        $payload = [
            'model' => $this->config->model,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"]],
                ],
            ]],
        ];

        $result = $this->chatCompletions($payload, 60, 'analyze');
        if ($result === null) {
            return $this->emptyAnalysis('Ошибка API');
        }

        $textResponse = $result['choices'][0]['message']['content'] ?? '{}';
        $parsed = $this->parseJsonObject(is_string($textResponse) ? $textResponse : '{}');

        if ($parsed === null) {
            error_log($this->logPrefix() . ' analyze parse error. Text: ' . json_encode($textResponse, JSON_UNESCAPED_UNICODE));
            return $this->emptyAnalysis('Не определено');
        }

        return $this->normalizeAnalysis($parsed);
    }

    public function getProductNutrients(string $productName): array
    {
        $prompt = "Ты — справочник калорийности продуктов. Верни КБЖУ на 100г для продукта \"$productName\".
Верни ТОЛЬКО валидный JSON без markdown и текста: {\"calories\": число, \"proteins\": число, \"fats\": число, \"carbs\": число}.
Название продукта может быть на русском или другом языке, но ответ должен содержать только числа.
Если не уверен в продукте или не знаешь значения — верни {\"calories\": 0, \"proteins\": 0, \"fats\": 0, \"carbs\": 0}.";

        $payload = [
            'model' => $this->config->model,
            'messages' => [[
                'role' => 'user',
                'content' => $prompt,
            ]],
        ];

        $result = $this->chatCompletions($payload, 45, 'getProductNutrients');
        if ($result === null) {
            return $this->emptyNutrients();
        }

        $textResponse = $result['choices'][0]['message']['content'] ?? '{}';
        $parsed = $this->parseJsonObject(is_string($textResponse) ? $textResponse : '{}');

        return $parsed ? $this->normalizeNutrients($parsed) : $this->emptyNutrients();
    }

    private function chatCompletions(array $payload, int $timeoutSeconds, string $operation): ?array
    {
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

        return $result;
    }

    private function parseJsonObject(string $text): ?array
    {
        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $cleaned, $matches) !== 1) {
            return null;
        }

        $parsed = json_decode($matches[0], true);

        return is_array($parsed) ? $parsed : null;
    }

    private function normalizeAnalysis(array $analysis): array
    {
        $food = trim((string)($analysis['food'] ?? 'не определено'));
        if ($food === '') {
            $food = 'не определено';
        }

        return [
            'food' => $food,
            'kcal' => max(0, (int)round((float)($analysis['kcal'] ?? 0))),
            'proteins' => max(0, round((float)($analysis['proteins'] ?? 0), 1)),
            'fats' => max(0, round((float)($analysis['fats'] ?? 0), 1)),
            'carbs' => max(0, round((float)($analysis['carbs'] ?? 0), 1)),
            'confidence' => max(0, min(1, round((float)($analysis['confidence'] ?? 0), 2))),
        ];
    }

    private function normalizeNutrients(array $nutrients): array
    {
        return [
            'calories' => max(0, round((float)($nutrients['calories'] ?? 0), 1)),
            'proteins' => max(0, round((float)($nutrients['proteins'] ?? 0), 1)),
            'fats' => max(0, round((float)($nutrients['fats'] ?? 0), 1)),
            'carbs' => max(0, round((float)($nutrients['carbs'] ?? 0), 1)),
        ];
    }

    private function emptyAnalysis(string $food): array
    {
        return [
            'food' => $food,
            'kcal' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbs' => 0,
            'confidence' => 0,
        ];
    }

    private function emptyNutrients(): array
    {
        return ['calories' => 0, 'proteins' => 0, 'fats' => 0, 'carbs' => 0];
    }

    private function logPrefix(): string
    {
        return 'AI provider [' . $this->config->provider . ']';
    }
}
