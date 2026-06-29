<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AI\MealRecommendationAIService;
use App\Services\DailyNutritionSummaryService;
use App\Services\MealRecommendationService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class MealRecommendationServiceTest extends TestCase
{
    public function testDetectsCurrentMealTypeWithTimezoneOffset(): void
    {
        $service = new MealRecommendationService(
            new FakeRecommendationDailySummaryService(null),
            new FakeRecommendationAiService()
        );

        $this->assertSame(
            'обед',
            $service->currentMealType(
                -180,
                new DateTimeImmutable('2026-05-22 09:30:00', new DateTimeZone('UTC'))
            )
        );
    }

    public function testBuildsRecommendationFromDailySummary(): void
    {
        $ai = new FakeRecommendationAiService([
            'meal_type' => 'ужин',
            'summary' => 'Нужно добрать белок без лишнего жира.',
            'suggestions' => [[
                'title' => 'Курица с рисом и овощами',
                'portion' => 'куриная грудка 150 г, рис 120 г, овощи 200 г',
                'reason' => 'поможет добрать белок и углеводы',
                'calories' => 520,
                'proteins' => 42.0,
                'fats' => 8.0,
                'carbs' => 62.0,
            ]],
        ]);
        $service = new MealRecommendationService(
            new FakeRecommendationDailySummaryService([
                'daily_goal' => 2200,
                'today_sum' => 1450,
                'remaining_calories' => 750,
                'today_macros' => ['proteins' => 82.0, 'fats' => 78.0, 'carbs' => 160.0],
                'macro_goals' => ['proteins_goal' => 130, 'fats_goal' => 65, 'carbs_goal' => 250],
            ]),
            $ai
        );

        $recommendation = $service->recommendForTelegramUser(
            100001,
            -180,
            new DateTimeImmutable('2026-05-22 15:00:00', new DateTimeZone('UTC'))
        );

        $this->assertStringContainsString('Что можно съесть сейчас (ужин):', (string)$recommendation);
        $this->assertStringContainsString('Курица с рисом и овощами', (string)$recommendation);
        $this->assertStringContainsString('КБЖУ: 520 ккал, Б 42 г, Ж 8 г, У 62 г', (string)$recommendation);
        $this->assertSame('ужин', $ai->lastContext['meal_type']);
        $this->assertSame(48.0, $ai->lastContext['macros']['proteins']['remaining']);
        $this->assertSame(-13.0, $ai->lastContext['macros']['fats']['remaining']);
    }
}

class FakeRecommendationDailySummaryService extends DailyNutritionSummaryService
{
    public function __construct(private readonly ?array $summary) {}

    public function getForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): ?array {
        return $this->summary;
    }
}

class FakeRecommendationAiService extends MealRecommendationAIService
{
    public array $lastContext = [];

    public function __construct(private readonly array $recommendation = []) {}

    public function recommend(array $context): array
    {
        $this->lastContext = $context;

        return $this->recommendation;
    }
}
