<?php

declare(strict_types=1);

namespace App\AI;

use App\Exceptions\AppException;

class DailyNutritionInsightAIService
{
    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser
    ) {}

    public function generate(array $context): array
    {
        $contextJson = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $prompt = <<<PROMPT
Ты — помощник по повседневному питанию. Проанализируй переданные данные за сегодня и предложи конкретные идеи для следующего приема пищи.

Правила:
- Верни только валидный JSON без markdown и комментариев.
- Пиши по-русски, спокойно и конкретно, без медицинских диагнозов.
- Вывод о уже съеденном делай только по переданному списку приемов, не выдумывай факты о рационе.
- Для следующего приема можно предлагать новые продукты, если они помогают приблизиться к оставшейся норме КБЖУ.
- Учитывай цель, дневную норму, съеденное, остаток КБЖУ и время следующего приема.
- Если какая-либо норма превышена, явно назови показатель и предложи, как скорректировать следующий прием.
- short_summary — одна фраза до 140 символов для главной страницы.
- day_analysis — 2–3 коротких предложения с выводом по текущему дню.
- advice — 1–2 предложения: какие КБЖУ стоит добрать или ограничить в следующем приеме.
- foods — ровно 3 идеи готовых блюд, а не отдельные общие продукты.
- Каждая идея должна содержать конкретное название блюда, основные составляющие и примерный размер порции.
- Не пиши общие варианты вроде «рыба», «овощи» или «каша». Пиши, например: «Запеченная треска 180 г с гречкой 150 г и овощным салатом».
- Идеи должны отличаться друг от друга и соответствовать target_calories и оставшемуся балансу КБЖУ.
- target_calories — реалистичная целевая калорийность следующего приема, неотрицательное целое число.

Формат:
{"short_summary":"Короткий вывод","day_analysis":"Развернутый вывод","next_meal":{"type":"ужин","advice":"Рекомендация","target_calories":450,"foods":["Запеченная треска 180 г с гречкой 150 г и овощным салатом","Куриное филе 170 г с рисом 130 г и брокколи","Омлет из 3 яиц с творогом 100 г и томатами"]}}

Данные:
{$contextJson}
PROMPT;

        $response = $this->client->complete([[
            'role' => 'user',
            'content' => $prompt,
        ]], 35, 'dailyNutritionInsight', [
            'model_purpose' => 'text',
            'max_tokens' => 420,
            'temperature' => 0.2,
            'json_schema' => $this->responseSchema(),
        ]);

        if ($response === null) {
            throw new AppException('AI-рекомендация временно недоступна', 502);
        }

        $parsed = $this->jsonParser->parseObject($response);
        if ($parsed === null) {
            error_log('Daily nutrition insight parse error');
            throw new AppException('AI вернул некорректную рекомендацию', 502);
        }

        return $this->normalize($parsed, $context);
    }

    private function normalize(array $result, array $context): array
    {
        $shortSummary = $this->limitText((string)($result['short_summary'] ?? ''), 140);
        $dayAnalysis = $this->limitText((string)($result['day_analysis'] ?? ''), 1200);
        $rawNextMeal = is_array($result['next_meal'] ?? null) ? $result['next_meal'] : [];
        $advice = $this->limitText((string)($rawNextMeal['advice'] ?? ''), 700);

        if ($shortSummary === '' || $dayAnalysis === '' || $advice === '') {
            throw new AppException('AI вернул неполную рекомендацию', 502);
        }

        $foods = [];
        foreach (array_slice((array)($rawNextMeal['foods'] ?? []), 0, 5) as $food) {
            $normalizedFood = $this->limitText((string)$food, 80);
            if ($normalizedFood !== '') {
                $foods[] = $normalizedFood;
            }
        }

        return [
            'short_summary' => $shortSummary,
            'day_analysis' => $dayAnalysis,
            'next_meal' => [
                'type' => $this->limitText(
                    (string)($rawNextMeal['type'] ?? $context['next_meal_type'] ?? 'прием пищи'),
                    40
                ),
                'advice' => $advice,
                'target_calories' => max(0, min(2000, (int)round((float)($rawNextMeal['target_calories'] ?? 0)))),
                'foods' => array_values(array_unique($foods)),
            ],
        ];
    }

    private function limitText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if (!function_exists('mb_strlen') || !function_exists('mb_substr')) {
            return strlen($value) > $limit ? substr($value, 0, $limit) : $value;
        }

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
    }

    private function responseSchema(): array
    {
        return [
            'name' => 'daily_nutrition_insight',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['short_summary', 'day_analysis', 'next_meal'],
                'properties' => [
                    'short_summary' => ['type' => 'string'],
                    'day_analysis' => ['type' => 'string'],
                    'next_meal' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'advice', 'target_calories', 'foods'],
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'advice' => ['type' => 'string'],
                            'target_calories' => ['type' => 'integer', 'minimum' => 0],
                            'foods' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'minItems' => 3,
                                'maxItems' => 3,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
