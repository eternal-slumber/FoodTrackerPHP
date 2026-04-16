<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\CalorieCalculatorService;
use App\ValueObjects\BodyMetrics;
use App\Enums\Gender;
use App\Enums\ActivityLevel;
use App\Enums\Goal;

use PDO;

class User
{
    public function __construct(
        public int $tgId,
        public float $weight,
        public int $height,
        public int $age,
        public string $gender,
        public string $activityLevel = 'medium',
        public string $goal = 'maintenance',
        public ?int $dailyGoal = null,
        public ?int $id = null
    ) {}

    public function save(): bool
    {
        $db = Database::getConnection();

        $genderEnum = Gender::fromValue($this->gender);
        $activityLevelEnum = ActivityLevel::fromValue($this->activityLevel);
        $goalEnum = Goal::fromValue($this->goal);

        $bodyMetrics = new BodyMetrics(
            age: $this->age,
            height: $this->height,
            weight: $this->weight,
            gender: $genderEnum
        );

        if ($this->dailyGoal === null) {
            $calculator = new CalorieCalculatorService();
            $this->dailyGoal = $calculator->calculate($bodyMetrics, $activityLevelEnum, $goalEnum);
        }

        $existing = self::findById($this->tgId);

        if ($existing === null) {
            $sql = "INSERT INTO users (tg_id, weight, height, age, gender, activity_level, goal, daily_goal) 
                    VALUES (:tg_id, :weight, :height, :age, :gender, :activity_level, :goal, :daily_goal)";
            $stmt = $db->prepare($sql);
            return $stmt->execute([
                'tg_id' => $this->tgId,
                'weight' => $this->weight,
                'height' => $this->height,
                'age' => $this->age,
                'gender' => $this->gender,
                'activity_level' => $this->activityLevel,
                'goal' => $this->goal,
                'daily_goal' => $this->dailyGoal,
            ]);
        }

        $sql = "UPDATE users SET 
                weight = :weight, height = :height, age = :age, 
                activity_level = :activity_level, goal = :goal, daily_goal = :daily_goal
                WHERE tg_id = :tg_id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'tg_id' => $this->tgId,
            'weight' => $this->weight,
            'height' => $this->height,
            'age' => $this->age,
            'activity_level' => $this->activityLevel,
            'goal' => $this->goal,
            'daily_goal' => $this->dailyGoal,
        ]);
    }

    public static function findById(int $tgId): ?self
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE tg_id = ?");
        $stmt->execute([$tgId]);

        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        $activityLevel = isset($data['activity_level']) 
            ? ActivityLevel::fromValue($data['activity_level'])->value 
            : 'medium';
        $goal = isset($data['goal']) 
            ? Goal::fromValue($data['goal'])->value 
            : 'maintenance';

        return new self(
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

    public static function getTodayCalories(int $userId): int
    {
        $db = Database::getConnection();
        
        $startOfDay = date('Y-m-d 00:00:00');
        $endOfDay = date('Y-m-d 23:59:59');
        
        $stmt = $db->prepare("
            SELECT SUM(calories) as total 
            FROM meals 
            WHERE user_id = ? 
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $startOfDay, $endOfDay]);
        
        $result = $stmt->fetch();
        return (int)($result['total'] ?? 0);
    }
}