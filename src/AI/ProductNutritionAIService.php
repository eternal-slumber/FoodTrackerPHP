<?php

declare(strict_types=1);

namespace App\AI;

class ProductNutritionAIService
{
    public function __construct(
        private readonly AIChatClientInterface $client,
        private readonly AIJsonResponseParser $jsonParser
    ) {}

    public function getProductNutrients(string $productName): array
    {
        $prompt = "Ты — справочник калорийности продуктов. Верни КБЖУ на 100г для продукта \"$productName\".
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
