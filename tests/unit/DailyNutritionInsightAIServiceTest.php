<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\AIChatClientInterface;
use App\AI\AIJsonResponseParser;
use App\AI\DailyNutritionInsightAIService;
use PHPUnit\Framework\TestCase;

class DailyNutritionInsightAIServiceTest extends TestCase
{
    public function testGeneratesNormalizedCompactInsightWithTextModelOptions(): void
    {
        $client = new FakeDailyInsightChatClient(json_encode([
            'short_summary' => '  Белка пока недостаточно.  ',
            'day_analysis' => 'Рацион умеренный по калориям. Белок стоит добрать.',
            'next_meal' => [
                'type' => 'ужин',
                'advice' => 'Добавьте нежирный белок и овощи.',
                'target_calories' => 460.8,
                'foods' => [
                    'Треска с гречкой и овощным салатом',
                    'Куриное филе с рисом и брокколи',
                    'Омлет с творогом и томатами',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));
        $service = new DailyNutritionInsightAIService($client, new AIJsonResponseParser());

        $result = $service->generate([
            'next_meal_type' => 'ужин',
            'meals' => [['name' => 'Каша']],
        ]);

        $this->assertSame('Белка пока недостаточно.', $result['short_summary']);
        $this->assertSame(461, $result['next_meal']['target_calories']);
        $this->assertSame([
            'Треска с гречкой и овощным салатом',
            'Куриное филе с рисом и брокколи',
            'Омлет с творогом и томатами',
        ], $result['next_meal']['foods']);
        $this->assertSame('text', $client->options['model_purpose']);
        $this->assertSame(420, $client->options['max_tokens']);
        $this->assertSame('daily_nutrition_insight', $client->options['json_schema']['name']);
        $this->assertStringContainsString('Каша', $client->prompt);
        $this->assertStringContainsString('ровно 3 идеи готовых блюд', $client->prompt);
        $this->assertStringContainsString('примерный размер порции', $client->prompt);
    }
}

class FakeDailyInsightChatClient implements AIChatClientInterface
{
    public array $options = [];
    public string $prompt = '';

    public function __construct(private readonly ?string $response) {}

    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): ?string {
        $this->options = $options;
        $this->prompt = (string)($messages[0]['content'] ?? '');

        return $this->response;
    }
}
