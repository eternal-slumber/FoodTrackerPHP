<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\AIInvalidResponseException;
use App\Exceptions\AppException;

class MealPhotoAnalysisAIService
{
    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser,
        private readonly MealPhotoAnalysisResponseValidator $responseValidator
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
- Допустимые диапазоны: weight 0–10000, kcal 0–10000, proteins/fats/carbs 0–1000.
- food — непустая строка длиной не более 120 символов.
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
            'temperature' => 0.1,
            'json_schema' => $this->responseSchema(),
        ]);

        $parsed = $this->jsonParser->parseObject($textResponse);
        if ($parsed === null) {
            error_log('Meal photo analysis parse error');
            throw new AIInvalidResponseException('AI вернул некорректный ответ', 502);
        }

        $analysis = $this->responseValidator->validateAndNormalize($parsed);
        if ($this->isUnrecognizedAnalysis($analysis)) {
            throw new AppException('Не удалось распознать еду. Попробуйте другое фото', 422);
        }

        return $analysis;
    }

    /**
     * @param array{
     *     food: string,
     *     weight: int,
     *     kcal: int,
     *     proteins: float,
     *     fats: float,
     *     carbs: float,
     *     confidence: float
     * } $analysis
     */
    private function isUnrecognizedAnalysis(array $analysis): bool
    {
        return preg_match('/^не определено$/iu', $analysis['food']) === 1
            && $analysis['weight'] === 0
            && $analysis['kcal'] === 0
            && $analysis['proteins'] === 0.0
            && $analysis['fats'] === 0.0
            && $analysis['carbs'] === 0.0
            && $analysis['confidence'] === 0.0;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        $macronutrient = ['type' => 'number', 'minimum' => 0, 'maximum' => 1000];

        return [
            'name' => 'meal_photo_analysis',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['food', 'weight', 'kcal', 'proteins', 'fats', 'carbs', 'confidence'],
                'properties' => [
                    'food' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                    'weight' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10_000],
                    'kcal' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10_000],
                    'proteins' => $macronutrient,
                    'fats' => $macronutrient,
                    'carbs' => $macronutrient,
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                ],
            ],
        ];
    }
}
