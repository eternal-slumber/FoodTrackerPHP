<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\AIChatClientInterface;
use App\AI\AIJsonResponseParser;
use App\AI\Exceptions\AIInvalidResponseException;
use App\AI\MealPhotoAnalysisAIService;
use App\AI\MealPhotoAnalysisResponseValidator;
use App\Exceptions\AppException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MealPhotoAnalysisAIServiceTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'foodtracker_ai_image_');
        self::assertIsString($path);
        $image = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        self::assertIsString($image);
        file_put_contents($path, $image);
        $this->imagePath = $path;
    }

    protected function tearDown(): void
    {
        if (is_file($this->imagePath)) {
            unlink($this->imagePath);
        }
    }

    public function testAcceptsNumbersAndNumericStringsAndNormalizesResult(): void
    {
        $client = new FakeMealPhotoChatClient(json_encode([
            'food' => '  Курица   с рисом  ',
            'weight' => '250.4',
            'kcal' => 520.6,
            'proteins' => '32.34',
            'fats' => 18,
            'carbs' => '54.15',
            'confidence' => '0.726',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $service = $this->service($client);

        $result = $service->analyze($this->imagePath);

        $this->assertSame([
            'food' => 'Курица с рисом',
            'weight' => 250,
            'kcal' => 521,
            'proteins' => 32.3,
            'fats' => 18.0,
            'carbs' => 54.2,
            'confidence' => 0.73,
        ], $result);
        $this->assertSame('vision', $client->options['model_purpose']);
        $this->assertSame('meal_photo_analysis', $client->options['json_schema']['name']);
        $this->assertTrue($client->options['json_schema']['strict']);
        $this->assertStringContainsString('0–10000', $client->prompt);
    }

    #[DataProvider('invalidAnalyses')]
    public function testRejectsInvalidAnalysisStructureOrValues(array $analysis): void
    {
        $client = new FakeMealPhotoChatClient(json_encode(
            $analysis,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        ));

        $this->expectException(AIInvalidResponseException::class);
        $this->service($client)->analyze($this->imagePath);
    }

    public function testRejectsMalformedJsonWithTypedException(): void
    {
        $client = new FakeMealPhotoChatClient('not json');

        $this->expectException(AIInvalidResponseException::class);
        $this->service($client)->analyze($this->imagePath);
    }

    public function testReturnsUnprocessableEntityWhenFoodWasNotRecognized(): void
    {
        $client = new FakeMealPhotoChatClient(json_encode([
            'food' => 'Не Определено',
            'weight' => 0,
            'kcal' => 0,
            'proteins' => 0,
            'fats' => 0,
            'carbs' => 0,
            'confidence' => 0,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $this->expectException(AppException::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Не удалось распознать еду. Попробуйте другое фото');
        $this->service($client)->analyze($this->imagePath);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidAnalyses(): iterable
    {
        $valid = self::validAnalysis();

        yield 'non-numeric macro' => [array_replace($valid, ['proteins' => 'много'])];
        yield 'null macro' => [array_replace($valid, ['proteins' => null])];
        yield 'array macro' => [array_replace($valid, ['carbs' => [20]])];
        yield 'boolean macro' => [array_replace($valid, ['fats' => true])];
        yield 'negative calories' => [array_replace($valid, ['kcal' => -1])];
        yield 'excessive calories' => [array_replace($valid, ['kcal' => 10_001])];
        yield 'excessive macro' => [array_replace($valid, ['proteins' => 1_001])];
        yield 'excessive weight' => [array_replace($valid, ['weight' => 10_001])];
        yield 'invalid confidence' => [array_replace($valid, ['confidence' => 1.01])];
        yield 'empty food name' => [array_replace($valid, ['food' => '   '])];
        yield 'non-string food name' => [array_replace($valid, ['food' => ['Суп']])];
        yield 'long food name' => [array_replace($valid, ['food' => str_repeat('я', 121)])];
        yield 'unknown products structure' => [array_replace($valid, ['products' => [$valid]])];

        $missingCalories = $valid;
        unset($missingCalories['kcal']);
        yield 'missing required field' => [$missingCalories];

        $missingConfidence = $valid;
        unset($missingConfidence['confidence']);
        yield 'missing confidence' => [$missingConfidence];

        yield 'list instead of object' => [[1, 2, 3]];
    }

    private function service(FakeMealPhotoChatClient $client): MealPhotoAnalysisAIService
    {
        return new MealPhotoAnalysisAIService(
            $client,
            new AIJsonResponseParser(),
            new MealPhotoAnalysisResponseValidator()
        );
    }

    /** @return array<string, mixed> */
    private static function validAnalysis(): array
    {
        return [
            'food' => 'Курица с рисом',
            'weight' => 250,
            'kcal' => 520,
            'proteins' => 32.3,
            'fats' => 18.5,
            'carbs' => 54.1,
            'confidence' => 0.72,
        ];
    }
}

final class FakeMealPhotoChatClient implements AIChatClientInterface
{
    /** @var array<string, mixed> */
    public array $options = [];
    public string $prompt = '';

    public function __construct(private readonly string $response) {}

    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): string {
        $content = $messages[0]['content'] ?? [];
        if (is_array($content)) {
            $this->prompt = (string)($content[0]['text'] ?? '');
        }
        $this->options = $options;

        return $this->response;
    }
}
