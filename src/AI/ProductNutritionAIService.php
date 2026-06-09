<?php

declare(strict_types=1);

namespace App\AI;

class ProductNutritionAIService
{
    private const PROCESSING_LABELS = [
        'fry' => 'жарка',
        'bake' => 'запекание',
        'boil' => 'варка',
        'stew' => 'тушение',
        'grill' => 'гриль',
        'steam' => 'приготовление на пару',
        'deep_fry' => 'фритюр',
        'no_oil_fry' => 'жарка без масла',
    ];

    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser
    ) {}

    public function getProductNutrients(string $productName, string $processing = ''): array
    {
        $processingContext = $this->processingContext($processing);
        $prompt = "Ты — справочник калорийности продуктов. Верни КБЖУ на 100г для продукта \"$productName\".
{$processingContext}
Верни ТОЛЬКО валидный JSON без markdown и текста: {\"calories\": число, \"proteins\": число, \"fats\": число, \"carbs\": число}.
Название продукта может быть на русском или другом языке, но ответ должен содержать только числа.
Если не уверен в продукте или не знаешь значения — верни {\"calories\": 0, \"proteins\": 0, \"fats\": 0, \"carbs\": 0}.";

        $textResponse = $this->client->complete([[
            'role' => 'user',
            'content' => $prompt,
        ]], 45, 'getProductNutrients');

        if ($textResponse === null) {
            return $this->emptyNutrients();
        }

        $parsed = $this->jsonParser->parseObject($textResponse);

        return $parsed ? $this->normalizeNutrients($parsed) : $this->emptyNutrients();
    }

    private function processingContext(string $processing): string
    {
        $label = self::PROCESSING_LABELS[$processing] ?? null;

        if ($label === null) {
            return '';
        }

        return "У пользователя выбран способ обработки: {$label}. Учитывай его как контекст продукта: например, \"яйца\" + \"варка\" означает варёные яйца.";
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

    private function emptyNutrients(): array
    {
        return ['calories' => 0, 'proteins' => 0, 'fats' => 0, 'carbs' => 0];
    }
}
