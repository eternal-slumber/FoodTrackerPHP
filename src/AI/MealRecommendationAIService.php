<?php

declare(strict_types=1);

namespace App\AI;

class MealRecommendationAIService
{
    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser
    ) {}

    public function recommend(array $context): array
    {
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
Ты — помощник по питанию. На основе текущего приема пищи и дневных КБЖУ предложи блюда, которые можно съесть сейчас.

Правила:
- Верни ТОЛЬКО валидный JSON без markdown, комментариев и пояснений.
- Все текстовые поля пиши на русском языке.
- Учитывай текущий прием пищи: завтрак, обед или ужин.
- Учитывай остаток калорий, белков, жиров и углеводов. Если показатель уже превышен, не увеличивай его без необходимости.
- Предложи от 1 до 3 конкретных вариантов.
- Порция должна быть реалистичной и вписываться в оставшиеся дневные нормы насколько возможно.
- calories — целое число, proteins/fats/carbs — числа в граммах.

Формат ответа:
{"meal_type":"обед","summary":"короткое объяснение","suggestions":[{"title":"название блюда","portion":"пример порции","reason":"почему подходит","calories":520,"proteins":42,"fats":12,"carbs":58}]}

Данные пользователя:
{$contextJson}
PROMPT;

        $textResponse = $this->client->complete([[
            'role' => 'user',
            'content' => $prompt,
        ]], 45, 'recommendMeal');

        if ($textResponse === null) {
            return $this->emptyRecommendation($context);
        }

        $parsed = $this->jsonParser->parseObject($textResponse);
        if ($parsed === null) {
            error_log('Meal recommendation parse error. Text: ' . json_encode($textResponse, JSON_UNESCAPED_UNICODE));
            return $this->emptyRecommendation($context);
        }

        return $this->normalizeRecommendation($parsed, $context);
    }

    private function normalizeRecommendation(array $recommendation, array $context): array
    {
        $suggestions = [];
        $rawSuggestions = $recommendation['suggestions'] ?? [];
        $rawSuggestions = is_array($rawSuggestions) ? array_slice($rawSuggestions, 0, 3) : [];

        foreach ($rawSuggestions as $rawSuggestion) {
            if (!is_array($rawSuggestion)) {
                continue;
            }

            $title = trim((string)($rawSuggestion['title'] ?? ''));
            $portion = trim((string)($rawSuggestion['portion'] ?? ''));
            $reason = trim((string)($rawSuggestion['reason'] ?? ''));

            if ($title === '') {
                continue;
            }

            $suggestions[] = [
                'title' => $title,
                'portion' => $portion !== '' ? $portion : 'порция по аппетиту',
                'reason' => $reason !== '' ? $reason : 'подходит под текущие дневные показатели',
                'calories' => max(0, (int)round((float)($rawSuggestion['calories'] ?? 0))),
                'proteins' => max(0, round((float)($rawSuggestion['proteins'] ?? 0), 1)),
                'fats' => max(0, round((float)($rawSuggestion['fats'] ?? 0), 1)),
                'carbs' => max(0, round((float)($rawSuggestion['carbs'] ?? 0), 1)),
            ];
        }

        return [
            'meal_type' => trim((string)($recommendation['meal_type'] ?? $context['meal_type'] ?? 'прием пищи')),
            'summary' => trim((string)($recommendation['summary'] ?? '')),
            'suggestions' => $suggestions,
        ];
    }

    private function emptyRecommendation(array $context): array
    {
        return [
            'meal_type' => (string)($context['meal_type'] ?? 'прием пищи'),
            'summary' => '',
            'suggestions' => [],
        ];
    }
}
