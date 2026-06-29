<?php

declare(strict_types=1);

namespace App\AI;

use App\Exceptions\AppException;

class MealPhotoAnalysisAIService
{
    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser
    ) {}

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
- weight оценивай в граммах для всей видимой съедобной порции на фото.
- kcal, proteins, fats и carbs оценивай для всей видимой порции, а не на 100 г.
- Значения должны быть числами: weight и kcal целые, proteins/fats/carbs можно с 1 знаком после запятой.
- confidence — число от 0 до 1.

Формат ответа:
{"food":"название на русском","weight":250,"kcal":520,"proteins":32.3,"fats":18.5,"carbs":54.1,"confidence":0.72}

Если оценка невозможна:
{"food":"не определено","weight":0,"kcal":0,"proteins":0,"fats":0,"carbs":0,"confidence":0}
PROMPT;

        $textResponse = $this->client->complete([[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageData}"]],
            ],
        ]], 60, 'analyze', [
            'model_purpose' => 'vision',
        ]);

        if ($textResponse === null) {
            throw new AppException('AI API не ответил вовремя или недоступен', 502);
        }

        $parsed = $this->jsonParser->parseObject($textResponse);
        if ($parsed === null) {
            error_log('Meal photo analysis parse error. Text: ' . json_encode($textResponse, JSON_UNESCAPED_UNICODE));
            throw new AppException('AI вернул некорректный ответ', 502);
        }

        return $this->normalizeAnalysis($parsed);
    }

    private function normalizeAnalysis(array $analysis): array
    {
        $food = trim((string)($analysis['food'] ?? 'не определено'));
        if ($food === '') {
            $food = 'не определено';
        }

        return [
            'food' => $food,
            'weight' => max(0, min(5000, (int)round((float)($analysis['weight'] ?? 0)))),
            'kcal' => max(0, (int)round((float)($analysis['kcal'] ?? 0))),
            'proteins' => max(0, round((float)($analysis['proteins'] ?? 0), 1)),
            'fats' => max(0, round((float)($analysis['fats'] ?? 0), 1)),
            'carbs' => max(0, round((float)($analysis['carbs'] ?? 0), 1)),
            'confidence' => max(0, min(1, round((float)($analysis['confidence'] ?? 0), 2))),
        ];
    }
}
