<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\DailyNutritionInsightAIService;
use App\Config\AIProviderConfig;
use App\Enums\Goal;
use App\Repositories\DailyNutritionInsightRepository;
use App\Repositories\MealRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

class DailyNutritionInsightService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly DailyNutritionSummaryService $dailySummary,
        private readonly DailyNutritionInsightRepository $insights,
        private readonly DailyNutritionInsightAIService $insightAi,
        private readonly AIProviderConfig $aiConfig
    ) {}

    public function getForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $snapshot = $this->buildSnapshot($telegramId, $timezoneOffsetMinutes, $nowUtc);

        if ($snapshot['context']['meals'] === []) {
            return $this->emptyResult($snapshot['local_date']);
        }

        $stored = $this->insights->findForUserAndDate($snapshot['user_id'], $snapshot['local_date']);
        if ($stored === null) {
            return [
                'state' => 'missing',
                'local_date' => $snapshot['local_date'],
                'generated_at' => null,
                'insight' => null,
            ];
        }

        return $this->storedResult(
            $stored,
            hash_equals((string)$stored['context_hash'], $snapshot['context_hash']) ? 'ready' : 'stale'
        );
    }

    public function refreshForTelegramUser(
        int $telegramId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $snapshot = $this->buildSnapshot($telegramId, $timezoneOffsetMinutes, $nowUtc);

        if ($snapshot['context']['meals'] === []) {
            $this->insights->deleteForUserAndDate($snapshot['user_id'], $snapshot['local_date']);

            return $this->emptyResult($snapshot['local_date']);
        }

        $stored = $this->insights->findForUserAndDate($snapshot['user_id'], $snapshot['local_date']);
        if ($stored !== null && hash_equals((string)$stored['context_hash'], $snapshot['context_hash'])) {
            return $this->storedResult($stored, 'ready', true);
        }

        $generated = $this->insightAi->generate($snapshot['context']);
        $generatedAt = $nowUtc->format('Y-m-d H:i:s');
        $this->insights->save(
            $snapshot['user_id'],
            $snapshot['local_date'],
            $timezoneOffsetMinutes,
            $snapshot['context_hash'],
            $generated,
            $this->aiConfig->textModel,
            $generatedAt
        );

        return [
            'state' => 'ready',
            'local_date' => $snapshot['local_date'],
            'generated_at' => $generatedAt,
            'cached' => false,
            'insight' => $generated,
        ];
    }

    private function buildSnapshot(
        int $telegramId,
        int $timezoneOffsetMinutes,
        ?DateTimeImmutable $nowUtc
    ): array {
        $this->assertValidTimezoneOffset($timezoneOffsetMinutes);
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $user = $this->users->findByTelegramId($telegramId);

        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $summary = $this->dailySummary->getForTelegramUser(
            $telegramId,
            $timezoneOffsetMinutes,
            $nowUtc
        );
        if ($summary === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $localNow = $nowUtc->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes));
        $localDate = $localNow->format('Y-m-d');
        $meals = $this->meals->findForLocalDay((int)$user->id, $timezoneOffsetMinutes, $nowUtc);
        $context = [
            'local_date' => $localDate,
            'goal' => Goal::fromValue($user->goal)->label(),
            'next_meal_type' => $this->nextMealType($meals),
            'calories' => [
                'consumed' => (int)$summary['today_sum'],
                'goal' => (int)$summary['daily_goal'],
                'remaining' => (int)$summary['remaining_calories'],
            ],
            'macros' => [
                'proteins' => $this->macroContext(
                    $summary['today_macros']['proteins'],
                    $summary['macro_goals']['proteins_goal']
                ),
                'fats' => $this->macroContext(
                    $summary['today_macros']['fats'],
                    $summary['macro_goals']['fats_goal']
                ),
                'carbs' => $this->macroContext(
                    $summary['today_macros']['carbs'],
                    $summary['macro_goals']['carbs_goal']
                ),
            ],
            'meals' => $meals,
        ];
        $encodedContext = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return [
            'user_id' => (int)$user->id,
            'local_date' => $localDate,
            'context' => $context,
            'context_hash' => hash('sha256', $encodedContext),
        ];
    }

    private function macroContext(float|int $consumed, float|int $goal): array
    {
        return [
            'consumed' => round((float)$consumed, 1),
            'goal' => round((float)$goal, 1),
            'remaining' => round((float)$goal - (float)$consumed, 1),
        ];
    }

    private function nextMealType(array $meals): string
    {
        if ($meals === []) {
            return 'следующий прием';
        }

        $lastMeal = $meals[array_key_last($meals)] ?? [];
        $time = (string)($lastMeal['time'] ?? '');
        $hour = preg_match('/^(\d{2}):\d{2}$/', $time, $matches) === 1
            ? (int)$matches[1]
            : 12;

        return match (true) {
            $hour < 10 => 'обед',
            $hour < 16 => 'ужин',
            $hour < 20 => 'легкий поздний прием',
            default => 'завтрак',
        };
    }

    private function storedResult(array $stored, string $state, bool $cached = false): array
    {
        return [
            'state' => $state,
            'local_date' => (string)$stored['local_date'],
            'generated_at' => (string)$stored['generated_at'],
            'cached' => $cached,
            'insight' => [
                'short_summary' => (string)$stored['short_summary'],
                'day_analysis' => (string)$stored['day_analysis'],
                'next_meal' => is_array($stored['next_meal']) ? $stored['next_meal'] : [],
            ],
        ];
    }

    private function emptyResult(string $localDate): array
    {
        return [
            'state' => 'empty',
            'local_date' => $localDate,
            'generated_at' => null,
            'insight' => null,
        ];
    }

    private function assertValidTimezoneOffset(int $timezoneOffsetMinutes): void
    {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }
    }
}
