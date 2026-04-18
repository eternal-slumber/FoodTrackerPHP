<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\Core\Database;
use App\Models\User;
use App\Services\CalorieCalculatorService;
use App\Validators\AnalyzeValidator;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use PDO;

class UserController
{
    private CalorieCalculatorService $calculator;

    public function __construct(private PDO $db)
    {
        $this->calculator = new CalorieCalculatorService();
    }

    #[RouteAttribute('/api/register', 'POST')]
    public function register(): string
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            return json_encode(['status' => 'error', 'message' => 'Пустые данные']);
        }

        try {
            $validated = UserValidator::validateRegistration($data);

            $user = new User(
                tgId: $validated['tg_id'],
                weight: $validated['weight'],
                height: $validated['height'],
                age: $validated['age'],
                gender: $validated['gender'],
                activityLevel: $validated['activity_level'],
                goal: $validated['goal']
            );

            if ($user->save()) {
                return json_encode([
                    'status' => 'success',
                    'daily_goal' => $user->dailyGoal,
                    'activity_level' => $user->activityLevel,
                    'goal' => $user->goal
                ]);
            }

            return json_encode(['status' => 'error', 'message' => 'Ошибка сохранения']);
        } catch (ValidationException $e) {
            $errors = $e->getContext()['validation_errors'] ?? [];
            return json_encode(['status' => 'error', 'message' => $e->getMessage(), 'errors' => $errors]);
        } catch (\Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    #[RouteAttribute('/api/user-status', 'GET')]
    public function status(): string
    {
        $tgId = AnalyzeValidator::validateTgId($_GET['tg_id'] ?? null);

        $user = User::findById($tgId);

        if ($user) {
            return json_encode([
                'registered' => true,
                'daily_goal' => $user->dailyGoal,
                'age' => $user->age,
                'height' => $user->height,
                'weight' => $user->weight,
                'gender' => $user->gender,
                'activity_level' => $user->activityLevel,
                'goal' => $user->goal
            ]);
        }

        return json_encode(['registered' => false]);
    }

    #[RouteAttribute('/api/progress', 'GET')]
    public function progress(): string
    {
        $tgId = AnalyzeValidator::validateTgId($_GET['tg_id'] ?? null);

        $user = User::findById($tgId);

        if (!$user) {
            return json_encode(['status' => 'error', 'message' => 'User not found']);
        }

        $todayCalories = $user->getTodayCalories($user->id);
        $dailyGoal = $user->dailyGoal ?? 0;
        $percentage = $dailyGoal > 0 ? round(($todayCalories / $dailyGoal) * 100, 1) : 0;

        return json_encode([
            'status' => 'success',
            'data' => [
                'daily_goal' => $dailyGoal,
                'today_sum' => $todayCalories,
                'percentage' => $percentage
            ]
        ]);
    }

    #[RouteAttribute('/api/delete-profile', 'POST')]
    public function delete(): string
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $tgId = AnalyzeValidator::validateTgId($data['tg_id'] ?? null);
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("DELETE FROM users WHERE tg_id = ?");
            $stmt->execute([$tgId]);

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Профиль удален']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Пользователь не найден']);
            }
        } catch (\PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Ошибка базы данных']);
        }
    }
}