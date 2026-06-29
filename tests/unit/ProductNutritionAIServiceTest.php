<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\AIChatClientInterface;
use App\AI\AIJsonResponseParser;
use App\AI\ProductNutritionAIService;
use PHPUnit\Framework\TestCase;

class ProductNutritionAIServiceTest extends TestCase
{
    public function testNormalizesProductNutrients(): void
    {
        $service = new ProductNutritionAIService(
            new FakeProductNutritionChatClient('{"calories":123.44,"proteins":10.22,"fats":-1,"carbs":25.26}'),
            new AIJsonResponseParser()
        );

        $nutrients = $service->getProductNutrients('Творог');

        $this->assertSame(123.4, $nutrients['calories']);
        $this->assertSame(10.2, $nutrients['proteins']);
        $this->assertSame(0, $nutrients['fats']);
        $this->assertSame(25.3, $nutrients['carbs']);
    }

    public function testAddsProcessingContextToPrompt(): void
    {
        $client = new FakeProductNutritionChatClient('{"calories":155,"proteins":13,"fats":11,"carbs":1}');
        $service = new ProductNutritionAIService($client, new AIJsonResponseParser());

        $service->getProductNutrients('2 яйца', 'boil');

        $this->assertStringContainsString('2 яйца', $client->lastPrompt);
        $this->assertStringContainsString('способ обработки: варка', $client->lastPrompt);
        $this->assertStringContainsString('варёные яйца', $client->lastPrompt);
        $this->assertSame('product_nutrition', $client->lastOptions['json_schema']['name']);
        $this->assertArrayNotHasKey('max_tokens', $client->lastOptions);
    }
}

class FakeProductNutritionChatClient implements AIChatClientInterface
{
    public string $lastPrompt = '';
    public array $lastOptions = [];

    public function __construct(private readonly ?string $response) {}

    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): ?string {
        $this->lastPrompt = (string)($messages[0]['content'] ?? '');
        $this->lastOptions = $options;

        return $this->response;
    }
}
