<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\AIChatClientInterface;
use App\AI\AIJsonResponseParser;
use App\AI\MealRecommendationAIService;
use PHPUnit\Framework\TestCase;

class MealRecommendationAIServiceTest extends TestCase
{
    public function testNormalizesStructuredRecommendations(): void
    {
        $service = new MealRecommendationAIService(
            new FakeRecommendationChatClient(json_encode([
                'meal_type' => 'обед',
                'summary' => 'Добираем белок.',
                'suggestions' => [[
                    'title' => 'Курица с гречкой',
                    'portion' => 'курица 150 г, гречка 120 г',
                    'reason' => 'много белка и умеренно жиров',
                    'calories' => 510.4,
                    'proteins' => 42.24,
                    'fats' => 11.96,
                    'carbs' => 56.11,
                ]],
            ], JSON_UNESCAPED_UNICODE)),
            new AIJsonResponseParser()
        );

        $recommendation = $service->recommend(['meal_type' => 'обед']);

        $this->assertSame('обед', $recommendation['meal_type']);
        $this->assertSame('Добираем белок.', $recommendation['summary']);
        $this->assertSame('Курица с гречкой', $recommendation['suggestions'][0]['title']);
        $this->assertSame(510, $recommendation['suggestions'][0]['calories']);
        $this->assertSame(42.2, $recommendation['suggestions'][0]['proteins']);
        $this->assertSame(12.0, $recommendation['suggestions'][0]['fats']);
        $this->assertSame(56.1, $recommendation['suggestions'][0]['carbs']);
    }

    public function testReturnsEmptyRecommendationWhenSuggestionsAreMissing(): void
    {
        $service = new MealRecommendationAIService(
            new FakeRecommendationChatClient('{}'),
            new AIJsonResponseParser()
        );

        $recommendation = $service->recommend(['meal_type' => 'ужин']);

        $this->assertSame('ужин', $recommendation['meal_type']);
        $this->assertSame([], $recommendation['suggestions']);
    }
}

class FakeRecommendationChatClient implements AIChatClientInterface
{
    public function __construct(private readonly ?string $response) {}

    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): ?string {
        return $this->response;
    }
}
