<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Goal;
use App\Repositories\DailyNutritionInsightRepository;
use App\Repositories\MealRepository;
use App\Repositories\SharedAccessLinkRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

class TrainerShareService
{

    public function __construct(
        private readonly UserRepository $users,
        private readonly MealRepository $meals,
        private readonly DailyNutritionInsightRepository $insights,
        private readonly SharedAccessLinkRepository $links,
        private readonly MacroGoalCalculationService $macroGoals
    ) {}

    public function getForOwner(int $telegramId, ?DateTimeImmutable $nowUtc = null): ?array
    {
        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            return null;
        }

        $now = $this->utcNow($nowUtc);
        $link = $this->links->findActiveForUser($user->id, $now->format('Y-m-d H:i:s'));

        return $link === null ? ['active' => false] : $this->ownerLink($link);
    }

    public function createForOwner(
        int $telegramId,
        string $displayName,
        int $timezoneOffset,
        ?DateTimeImmutable $nowUtc = null,
        string $duration = '30',
        array $visibility = []
    ): array {
        if ($timezoneOffset < -840 || $timezoneOffset > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $now = $this->utcNow($nowUtc);
        [$durationDays, $expiresAt] = $this->accessPeriod($duration, $now);
        $visibility = $this->normalizeVisibility($visibility);
        $name = trim(strip_tags($displayName));
        $name = $name !== '' ? mb_substr($name, 0, 120) : 'Пользователь FoodTracker';
        $link = $this->links->replaceForUser(
            $user->id,
            bin2hex(random_bytes(32)),
            $name,
            $timezoneOffset,
            $expiresAt,
            $now->format('Y-m-d H:i:s'),
            $durationDays,
            $visibility
        );

        return [
            'active' => true,
            'token' => (string)$link['token'],
            'timezone_offset' => $timezoneOffset,
            'duration' => $duration,
            'expires_at' => $expiresAt,
            'visibility' => $visibility,
            'last_viewed_at' => null,
        ];
    }

    public function updateForOwner(
        int $telegramId,
        string $duration,
        array $visibility,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $now = $this->utcNow($nowUtc);
        $active = $this->links->findActiveForUser($user->id, $now->format('Y-m-d H:i:s'));
        if ($active === null) {
            throw new \InvalidArgumentException('Active trainer link not found');
        }

        [$durationDays, $expiresAt] = $this->accessPeriod($duration, $now);
        $visibility = $this->normalizeVisibility($visibility);
        $this->links->updateActiveForUser($user->id, $expiresAt, $durationDays, $visibility);

        return $this->ownerLink([
            ...$active,
            'expires_at' => $expiresAt,
            'duration_days' => $durationDays,
            'show_meals' => $visibility['meals'],
            'show_nutrition' => $visibility['nutrition'],
            'show_history' => $visibility['history'],
            'show_ai_analysis' => $visibility['ai_analysis'],
            'show_profile_params' => $visibility['profile_params'],
        ]);
    }

    public function revokeForOwner(int $telegramId, ?DateTimeImmutable $nowUtc = null): void
    {
        $user = $this->users->findByTelegramId($telegramId);
        if ($user === null || $user->id === null) {
            throw new \InvalidArgumentException('User not found');
        }

        $this->links->revokeForUser(
            $user->id,
            $this->utcNow($nowUtc)->format('Y-m-d H:i:s')
        );
    }

    public function getPublicDiary(string $token, ?DateTimeImmutable $nowUtc = null): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $now = $this->utcNow($nowUtc);
        $link = $this->links->findActiveByToken($token, $now->format('Y-m-d H:i:s'));
        if ($link === null) {
            return null;
        }

        $timezoneOffset = (int)$link['timezone_offset'];
        $localNow = $now->modify(sprintf('%+d minutes', -$timezoneOffset));
        $today = $localNow->format('Y-m-d');
        $dailyGoal = (int)($link['daily_goal'] ?? 0);
        $nutrition = $this->users->getTodayNutrition((int)$link['user_id'], $timezoneOffset, $now);
        $macroGoals = $this->macroGoals->calculate(
            $dailyGoal,
            (float)$link['weight'],
            Goal::fromValue((string)$link['goal'])
        );
        $visibility = $this->visibilityFromLink($link);
        $days = $visibility['history']
            ? $this->recentDays(
                (int)$link['user_id'],
                $timezoneOffset,
                $localNow,
                $visibility['meals'],
                $visibility['nutrition']
            )
            : [];
        $insight = $visibility['ai_analysis']
            ? $this->insights->findForUserAndDate((int)$link['user_id'], $today)
            : null;

        $this->links->touchViewed((int)$link['id'], $now->format('Y-m-d H:i:s'));

        return [
            'display_name' => (string)$link['display_name'],
            'today' => $today,
            'today_label' => $this->formatRussianDate($localNow),
            'expires_at' => $link['expires_at'] !== null ? (string)$link['expires_at'] : null,
            'visibility' => $visibility,
            'summary' => !$visibility['nutrition'] ? null : [
                'calories' => $nutrition['calories'],
                'daily_goal' => $dailyGoal,
                'remaining' => $dailyGoal - $nutrition['calories'],
                'proteins' => $nutrition['proteins'],
                'fats' => $nutrition['fats'],
                'carbs' => $nutrition['carbs'],
                'macro_goals' => $macroGoals,
            ],
            'today_meals' => !$visibility['meals'] ? null : $this->meals->findForLocalDay(
                (int)$link['user_id'],
                $timezoneOffset,
                $now
            ),
            'recent_days' => !$visibility['history'] ? null : $days,
            'average_7_days' => !$visibility['history'] || !$visibility['nutrition']
                ? null
                : $this->averageCalories($days),
            'ai_analysis' => $insight === null ? null : [
                'summary' => (string)$insight['short_summary'],
                'analysis' => (string)$insight['day_analysis'],
            ],
            'profile_params' => !$visibility['profile_params'] ? null : [
                'weight' => round((float)$link['weight'], 1),
                'height' => (int)$link['height'],
                'age' => (int)$link['age'],
                'gender' => (string)$link['gender'],
                'goal' => Goal::fromValue((string)$link['goal'])->label(),
            ],
        ];
    }

    private function recentDays(
        int $userId,
        int $timezoneOffset,
        DateTimeImmutable $localNow,
        bool $includeMeals,
        bool $includeNutrition
    ): array
    {
        $startDate = $localNow->modify('-6 days')->format('Y-m-d');
        $months = array_values(array_unique([
            $localNow->modify('-6 days')->format('Y-m'),
            $localNow->format('Y-m'),
        ]));
        $days = [];

        foreach ($months as $month) {
            foreach ($this->meals->getDailyCaloriesForMonth($userId, $month, $timezoneOffset) as $day) {
                $date = (string)$day['date'];
                if ($date < $startDate || $date > $localNow->format('Y-m-d')) {
                    continue;
                }
                unset($day['weight']);
                $day['meal_count'] = count($day['meals'] ?? []);
                $day['meals'] = !$includeMeals ? [] : array_map(
                    static function (array $meal): array {
                        unset($meal['thumbnail_url'], $meal['weight']);
                        return $meal;
                    },
                    $day['meals'] ?? []
                );
                if (!$includeNutrition) {
                    unset($day['calories'], $day['proteins'], $day['fats'], $day['carbs'], $day['percentage']);
                }
                $days[$date] = $day;
            }
        }

        ksort($days);
        return array_values($days);
    }

    private function averageCalories(array $days): int
    {
        return $days === []
            ? 0
            : (int)round(array_sum(array_column($days, 'calories')) / count($days));
    }

    private function ownerLink(array $link): array
    {
        return [
            'active' => true,
            'token' => (string)$link['token'],
            'duration' => $link['duration_days'] === null ? 'unlimited' : (string)$link['duration_days'],
            'expires_at' => $link['expires_at'] !== null ? (string)$link['expires_at'] : null,
            'visibility' => $this->visibilityFromLink($link),
            'last_viewed_at' => $link['last_viewed_at'] !== null ? (string)$link['last_viewed_at'] : null,
        ];
    }

    /** @return array{0:?int,1:?string} */
    private function accessPeriod(string $duration, DateTimeImmutable $now): array
    {
        return match ($duration) {
            '7' => [7, $now->modify('+7 days')->format('Y-m-d H:i:s')],
            '30' => [30, $now->modify('+30 days')->format('Y-m-d H:i:s')],
            'unlimited' => [null, null],
            default => throw new \InvalidArgumentException('Invalid trainer link duration'),
        };
    }

    private function normalizeVisibility(array $visibility): array
    {
        $defaults = [
            'meals' => true,
            'nutrition' => true,
            'history' => true,
            'ai_analysis' => true,
            'profile_params' => false,
        ];
        $result = [];

        foreach ($defaults as $key => $default) {
            $value = $visibility[$key] ?? $default;
            if (!is_bool($value)) {
                throw new \InvalidArgumentException('Invalid trainer link visibility');
            }
            $result[$key] = $value;
        }

        if (!in_array(true, $result, true)) {
            throw new \InvalidArgumentException('At least one shared section is required');
        }

        return $result;
    }

    private function visibilityFromLink(array $link): array
    {
        return [
            'meals' => (bool)($link['show_meals'] ?? true),
            'nutrition' => (bool)($link['show_nutrition'] ?? true),
            'history' => (bool)($link['show_history'] ?? true),
            'ai_analysis' => (bool)($link['show_ai_analysis'] ?? true),
            'profile_params' => (bool)($link['show_profile_params'] ?? false),
        ];
    }

    private function utcNow(?DateTimeImmutable $nowUtc): DateTimeImmutable
    {
        return ($nowUtc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
    }

    private function formatRussianDate(DateTimeImmutable $date): string
    {
        $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
            'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

        return (int)$date->format('j') . ' ' . $months[(int)$date->format('n')];
    }
}
