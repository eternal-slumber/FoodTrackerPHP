<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\CurrentUser;
use App\Attributes\RouteAttribute;
use App\Enums\Goal;
use App\Http\ResponseResponder;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\MacroGoalCalculationService;
use App\Services\RateLimiterService;
use App\Services\SummaryService;
use App\Services\UploadedFileStorage;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RateLimiterService $rateLimiter,
        private readonly SummaryService $summaryService,
        private readonly MacroGoalCalculationService $macroGoalCalculator,
        private readonly UploadedFileStorage $storage
    ) {}

    #[RouteAttribute('/api/register', 'POST')]
    public function register(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];
            $data['tg_id'] = $currentUser->telegramId;

            if (!$data) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Пустые данные'], 400);
            }

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'register', 10, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

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

            if ($this->users->save($user)) {
                return ResponseResponder::json($response, [
                    'status' => 'success',
                    'daily_goal' => $user->dailyGoal,
                    'activity_level' => $user->activityLevel,
                    'goal' => $user->goal
                ]);
            }

            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Ошибка сохранения'], 500);
        } catch (ValidationException $e) {
            $errors = $e->getContext()['validation_errors'] ?? [];
            return ResponseResponder::json($response, ['status' => 'error', 'message' => $e->getMessage(), 'errors' => $errors], 400);
        } catch (\Exception $e) {
            error_log('Register error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/user-status', 'GET')]
    public function status(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $user = $this->users->findByTelegramId($currentUser->telegramId);

            if ($user) {
                return ResponseResponder::json($response, [
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

            return ResponseResponder::json($response, ['registered' => false]);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        }
    }

    #[RouteAttribute('/api/profile', 'POST')]
    public function updateProfile(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $data = $request->getParsedBody();
            $data = is_array($data) ? $data : [];
            $data['tg_id'] = $currentUser->telegramId;

            if (!$data) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Пустые данные'], 400);
            }

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'update_profile', 20, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

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

            if (!$this->users->save($user)) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Ошибка сохранения'], 500);
            }

            return ResponseResponder::json($response, [
                'status' => 'success',
                'daily_goal' => $user->dailyGoal,
                'age' => $user->age,
                'height' => $user->height,
                'weight' => $user->weight,
                'gender' => $user->gender,
                'activity_level' => $user->activityLevel,
                'goal' => $user->goal
            ]);
        } catch (ValidationException $e) {
            $errors = $e->getContext()['validation_errors'] ?? [];
            return ResponseResponder::json($response, ['status' => 'error', 'message' => $e->getMessage(), 'errors' => $errors], 400);
        } catch (\Exception $e) {
            error_log('Update profile error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/progress', 'GET')]
    public function progress(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $user = $this->users->findByTelegramId($currentUser->telegramId);

            if (!$user) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'User not found'], 404);
            }

            $params = $request->getQueryParams();
            $timezoneOffset = isset($params['tz_offset']) ? (int)$params['tz_offset'] : 0;

            if ($timezoneOffset < -840 || $timezoneOffset > 840) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Invalid timezone offset'], 400);
            }

            $todayNutrition = $this->users->getTodayNutrition((int)$user->id, $timezoneOffset);
            $todayCalories = $todayNutrition['calories'];
            $dailyGoal = $user->dailyGoal ?? 0;
            $percentage = $dailyGoal > 0 ? round(($todayCalories / $dailyGoal) * 100, 1) : 0;
            $macroGoals = $this->macroGoalCalculator->calculate(
                $dailyGoal,
                $user->weight,
                Goal::fromValue($user->goal)
            );
            $macroPercentages = [
                'proteins' => $this->calculatePercentage($todayNutrition['proteins'], $macroGoals['proteins_goal']),
                'fats' => $this->calculatePercentage($todayNutrition['fats'], $macroGoals['fats_goal']),
                'carbs' => $this->calculatePercentage($todayNutrition['carbs'], $macroGoals['carbs_goal']),
            ];

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => [
                    'daily_goal' => $dailyGoal,
                    'today_sum' => $todayCalories,
                    'percentage' => $percentage,
                    'macro_goals' => $macroGoals,
                    'today_macros' => [
                        'proteins' => $todayNutrition['proteins'],
                        'fats' => $todayNutrition['fats'],
                        'carbs' => $todayNutrition['carbs'],
                    ],
                    'macro_percentages' => $macroPercentages,
                ]
            ]);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        }
    }

    #[RouteAttribute('/api/summary', 'GET')]
    public function summary(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $params = $request->getQueryParams();
            $month = isset($params['month']) ? (string)$params['month'] : date('Y-m');
            $timezoneOffset = isset($params['tz_offset']) ? (int)$params['tz_offset'] : 0;

            $summary = $this->summaryService->getMonthlySummary(
                $currentUser->telegramId,
                $month,
                $timezoneOffset
            );

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $summary,
            ]);
        } catch (\InvalidArgumentException $e) {
            return ResponseResponder::json($response, ['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (\Exception $e) {
            error_log('Summary error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    #[RouteAttribute('/api/delete-profile', 'POST')]
    public function delete(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);

            if (!$this->rateLimiter->consume('tg:' . $currentUser->telegramId, 'delete_profile', 5, 3600)) {
                return ResponseResponder::json($response, ['error' => 'Too Many Requests'], 429);
            }

            if ($this->users->deleteByTelegramId($currentUser->telegramId) > 0) {
                try {
                    $this->storage->deleteUserFolder($currentUser->telegramId);
                } catch (\Throwable $e) {
                    error_log('Delete profile upload cleanup error: ' . $e->getMessage());
                }

                return ResponseResponder::json($response, ['status' => 'success', 'message' => 'Профиль удален']);
            } else {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Пользователь не найден'], 404);
            }
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        } catch (\PDOException $e) {
            error_log('Delete profile database error: ' . $e->getMessage());
            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Ошибка базы данных'], 500);
        }
    }

    private function currentUser(Request $request): CurrentUser
    {
        $currentUser = $request->getAttribute(TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE);

        if (!$currentUser instanceof CurrentUser) {
            throw new \RuntimeException('Authenticated Telegram user is missing');
        }

        return $currentUser;
    }

    private function calculatePercentage(float|int $current, float|int $goal): float
    {
        return $goal > 0 ? round(((float)$current / (float)$goal) * 100, 1) : 0.0;
    }
}
