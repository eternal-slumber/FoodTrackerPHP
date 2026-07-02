<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DailyNutritionInsightService;
use App\Services\MealRecommendationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MealRecommendationServiceTest extends TestCase
{
    public function testFormatsRecommendationFromSharedDailyInsight(): void
    {
        $insight = new FakeRecommendationDailyInsightService([
            'state' => 'ready',
            'insight' => [
                'next_meal' => [
                    'type' => 'ужин',
                    'advice' => 'Нужно добрать белок без лишнего жира.',
                    'target_calories' => 520,
                    'foods' => [
                        'Курица с рисом и овощами',
                        'Треска с гречкой и салатом',
                        'Омлет с творогом и томатами',
                    ],
                ],
            ],
        ]);
        $service = new MealRecommendationService($insight);
        $now = new DateTimeImmutable('2026-05-22 15:00:00');

        $recommendation = $service->recommendForTelegramUser(100001, -180, $now);

        $this->assertStringContainsString('Что можно съесть сейчас (ужин):', (string)$recommendation);
        $this->assertStringContainsString('Нужно добрать белок без лишнего жира.', (string)$recommendation);
        $this->assertStringContainsString('1. Курица с рисом и овощами', (string)$recommendation);
        $this->assertStringContainsString('Ориентир на прием: около 520 ккал.', (string)$recommendation);
        $this->assertSame([100001, -180, $now], $insight->lastArguments);
    }

    public function testExplainsWhenThereAreNoMealsYet(): void
    {
        $service = new MealRecommendationService(
            new FakeRecommendationDailyInsightService(['state' => 'empty', 'insight' => null])
        );

        $recommendation = $service->recommendForTelegramUser(100001);

        $this->assertStringContainsString('Добавь первый прием пищи', (string)$recommendation);
    }

    public function testReturnsNullForUnknownUser(): void
    {
        $service = new MealRecommendationService(new FakeRecommendationDailyInsightService(null));

        $this->assertNull($service->recommendForTelegramUser(999999));
    }
}

class FakeRecommendationDailyInsightService extends DailyNutritionInsightService
{
    public array $lastArguments = [];

    public function __construct(private readonly ?array $result) {}

    public function refreshForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $this->lastArguments = [$telegramId, $timezoneOffsetMinutes, $nowUtc];

        if ($this->result === null) {
            throw new \InvalidArgumentException('User not found');
        }

        return $this->result;
    }
}
