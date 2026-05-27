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
}

class FakeProductNutritionChatClient implements AIChatClientInterface
{
    public function __construct(private readonly ?string $response) {}

    public function complete(array $messages, int $timeoutSeconds, string $operation): ?string
    {
        return $this->response;
    }
}
