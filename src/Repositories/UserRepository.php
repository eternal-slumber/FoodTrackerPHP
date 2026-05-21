<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\Goal;
use App\Models\User;
use App\Services\CalorieCalculatorService;
use App\ValueObjects\BodyMetrics;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

class UserRepository
{
    public function __construct(
        private readonly PDO $db,
        private readonly CalorieCalculatorService $calculator
    ) {}

    public function save(User $user): bool
    {
        $genderEnum = Gender::fromValue($user->gender);
        $activityLevelEnum = ActivityLevel::fromValue($user->activityLevel);
        $goalEnum = Goal::fromValue($user->goal);

        $bodyMetrics = new BodyMetrics(
            age: $user->age,
            height: $user->height,
            weight: $user->weight,
            gender: $genderEnum
        );

        if ($user->dailyGoal === null) {
            $user->dailyGoal = $this->calculator->calculate($bodyMetrics, $activityLevelEnum, $goalEnum);
        }

        if ($this->findByTelegramId($user->tgId) === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO users (tg_id, weight, height, age, gender, activity_level, goal, daily_goal)
                 VALUES (:tg_id, :weight, :height, :age, :gender, :activity_level, :goal, :daily_goal)'
            );

            return $stmt->execute([
                'tg_id' => $user->tgId,
                'weight' => $user->weight,
                'height' => $user->height,
                'age' => $user->age,
                'gender' => $user->gender,
                'activity_level' => $user->activityLevel,
                'goal' => $user->goal,
                'daily_goal' => $user->dailyGoal,
            ]);
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET weight = :weight, height = :height, age = :age,
             gender = :gender, activity_level = :activity_level, goal = :goal, daily_goal = :daily_goal
             WHERE tg_id = :tg_id'
        );

        return $stmt->execute([
            'tg_id' => $user->tgId,
            'weight' => $user->weight,
            'height' => $user->height,
            'age' => $user->age,
            'gender' => $user->gender,
            'activity_level' => $user->activityLevel,
            'goal' => $user->goal,
            'daily_goal' => $user->dailyGoal,
        ]);
    }

    public function findByTelegramId(int $telegramId): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE tg_id = ?');
        $stmt->execute([$telegramId]);
        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function deleteByTelegramId(int $telegramId): int
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE tg_id = ?');
        $stmt->execute([$telegramId]);

        return $stmt->rowCount();
    }

    public function getTodayCalories(int $userId, int $timezoneOffsetMinutes = 0): int
    {
        return $this->getTodayNutrition($userId, $timezoneOffsetMinutes)['calories'];
    }

    public function getTodayNutrition(
        int $userId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        [$startOfDay, $endOfDay] = $this->getLocalDayUtcRange($timezoneOffsetMinutes, $nowUtc);

        $stmt = $this->db->prepare(
            'SELECT
                COALESCE(SUM(calories), 0) AS calories,
                COALESCE(SUM(proteins), 0) AS proteins,
                COALESCE(SUM(fats), 0) AS fats,
                COALESCE(SUM(carbs), 0) AS carbs
             FROM meals
             WHERE user_id = ? AND created_at >= ? AND created_at < ?'
        );
        $stmt->execute([
            $userId,
            $startOfDay->format('Y-m-d H:i:s'),
            $endOfDay->format('Y-m-d H:i:s'),
        ]);
        $result = $stmt->fetch();

        return [
            'calories' => (int)($result['calories'] ?? 0),
            'proteins' => round((float)($result['proteins'] ?? 0), 1),
            'fats' => round((float)($result['fats'] ?? 0), 1),
            'carbs' => round((float)($result['carbs'] ?? 0), 1),
        ];
    }

    private function getLocalDayUtcRange(int $timezoneOffsetMinutes, ?DateTimeImmutable $nowUtc = null): array
    {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localModifier = sprintf('%+d minutes', -$timezoneOffsetMinutes);
        $utcModifier = sprintf('%+d minutes', $timezoneOffsetMinutes);
        $localDate = $nowUtc->modify($localModifier)->format('Y-m-d');

        $startLocal = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $localDate . ' 00:00:00',
            new DateTimeZone('UTC')
        );

        if (!$startLocal instanceof DateTimeImmutable) {
            throw new \RuntimeException('Failed to build local day range');
        }

        return [
            $startLocal->modify($utcModifier),
            $startLocal->modify('+1 day')->modify($utcModifier),
        ];
    }

    private function hydrate(array $data): User
    {
        $activityLevel = isset($data['activity_level'])
            ? ActivityLevel::fromValue($data['activity_level'])->value
            : ActivityLevel::MEDIUM->value;
        $goal = isset($data['goal'])
            ? Goal::fromValue($data['goal'])->value
            : Goal::MAINTENANCE->value;

        return new User(
            tgId: (int)$data['tg_id'],
            weight: (float)$data['weight'],
            height: (int)$data['height'],
            age: (int)$data['age'],
            gender: $data['gender'],
            activityLevel: $activityLevel,
            goal: $goal,
            dailyGoal: (int)$data['daily_goal'],
            id: (int)$data['id']
        );
    }
}
