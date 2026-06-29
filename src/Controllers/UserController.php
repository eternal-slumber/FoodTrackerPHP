<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\CurrentUser;
use App\Attributes\RouteAttribute;
use App\Config\TelegramAuthConfig;
use App\Enums\Goal;
use App\Http\ResponseResponder;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DailyNutritionSummaryService;
use App\Services\DailyNutritionInsightService;
use App\Services\AiQuotaService;
use App\Services\MacroGoalCalculationService;
use App\Services\NutritionStreakService;
use App\Services\RateLimiterService;
use App\Services\SummaryService;
use App\Services\TelemetryService;
use App\Services\UploadedFileStorage;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use App\Exceptions\AppException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RateLimiterService $rateLimiter,
        private readonly AiQuotaService $aiQuota,
        private readonly SummaryService $summaryService,
        private readonly DailyNutritionSummaryService $dailyNutritionSummary,
        private readonly MacroGoalCalculationService $macroGoalCalculator,
        private readonly DailyNutritionInsightService $dailyNutritionInsight,
        private readonly NutritionStreakService $nutritionStreak,
        private readonly UploadedFileStorage $storage,
        private readonly TelemetryService $telemetry,
        private readonly TelegramAuthConfig $telegramAuthConfig
    ) {}

    private function currentUser(Request $request): CurrentUser
    {
        $currentUser = $request->getAttribute(TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE);

        if (!$currentUser instanceof CurrentUser) {
            throw new \RuntimeException('Authenticated Telegram user is missing');
        }

        return $currentUser;
    }

    #[RouteAttribute('/api/events/app-opened', 'POST')]
    public function appOpened(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $user = $this->users->findByTelegramId($currentUser->telegramId);
            $eventData = null;
            if ($user === null && $this->isDevCurrentUser($currentUser)) {
                $eventData = [
                    'dev' => true,
                    'dev_telegram_id' => $currentUser->telegramId,
                ];
            }

            $this->telemetry->recordUserEvent(
                $user?->id !== null ? (int)$user->id : null,
                'app_opened',
                $eventData,
                $request
            );

            return ResponseResponder::json($response, ['status' => 'success']);
        } catch (\Exception $e) {
            error_log('App opened event error: ' . $e->getMessage());
            $this->telemetry->recordSystemError('error', 'user_events', $e->getMessage(), [
                'action' => 'app_opened',
            ], $e);

            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

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
            $existingUser = $this->users->findByTelegramId($currentUser->telegramId);

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
                if ($existingUser === null) {
                    $registeredUser = $this->users->findByTelegramId($currentUser->telegramId);
                    $this->telemetry->recordUserEvent(
                        $registeredUser?->id !== null ? (int)$registeredUser->id : null,
                        'user_registered',
                        ['source' => 'mini_app'],
                        $request
                    );
                }

                $macroGoals = $this->macroGoalCalculator->calculate(
                    (int)$user->dailyGoal,
                    $user->weight,
                    Goal::fromValue($user->goal)
                );

                return ResponseResponder::json($response, [
                    'status' => 'success',
                    'daily_goal' => $user->dailyGoal,
                    'macro_goals' => $macroGoals,
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
            $this->telemetry->recordSystemError('error', 'users', $e->getMessage(), [
                'action' => 'register',
            ], $e);

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
            $this->telemetry->recordSystemError('error', 'users', $e->getMessage(), [
                'action' => 'update_profile',
            ], $e);

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

            $summary = $this->dailyNutritionSummary->getForTelegramUser($currentUser->telegramId, $timezoneOffset);
            if ($summary === null) {
                return ResponseResponder::json($response, ['status' => 'error', 'message' => 'User not found'], 404);
            }

            $summary['streak'] = $this->nutritionStreak->getForUser(
                (int)$user->id,
                $timezoneOffset
            );

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $summary
            ]);
        } catch (ValidationException $e) {
            return ResponseResponder::json($response, $e->toArray(), 400);
        }
    }

    #[RouteAttribute('/api/daily-insight', 'GET')]
    public function dailyInsight(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $timezoneOffset = $this->timezoneOffsetFromRequest($request);

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $this->dailyNutritionInsight->getForTelegramUser(
                    $currentUser->telegramId,
                    $timezoneOffset
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log('Daily nutrition insight error: ' . $e->getMessage());

            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'Не удалось загрузить AI-рекомендацию',
            ], 500);
        }
    }

    #[RouteAttribute('/api/daily-insight/refresh', 'POST')]
    public function refreshDailyInsight(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->currentUser($request);
            $timezoneOffset = $this->timezoneOffsetFromRequest($request);
            if (!$this->aiQuota->consumeInsightBurst($currentUser->telegramId)) {
                return ResponseResponder::json($response, [
                    'status' => 'error',
                    'message' => 'AI-рекомендация уже обновляется',
                ], 429);
            }

            if (!$this->aiQuota->consumeInsight($currentUser->telegramId)) {
                return ResponseResponder::json($response, [
                    'status' => 'error',
                    'message' => 'Лимит обновлений AI-рекомендации временно исчерпан',
                ], 429);
            }

            return ResponseResponder::json($response, [
                'status' => 'success',
                'data' => $this->dailyNutritionInsight->refreshForTelegramUser(
                    $currentUser->telegramId,
                    $timezoneOffset
                ),
            ]);
        } catch (AppException $e) {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 502);
        } catch (\InvalidArgumentException $e) {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            error_log('Refresh daily nutrition insight error: ' . $e->getMessage());
            $this->telemetry->recordSystemError('error', 'ai', $e->getMessage(), [
                'action' => 'daily_nutrition_insight',
            ], $e);

            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'Не удалось обновить AI-рекомендацию',
            ], 500);
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
            $this->telemetry->recordSystemError('error', 'users', $e->getMessage(), [
                'action' => 'summary',
            ], $e);

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
            $this->telemetry->recordSystemError('error', 'users', $e->getMessage(), [
                'action' => 'delete_profile',
            ], $e);

            return ResponseResponder::json($response, ['status' => 'error', 'message' => 'Ошибка базы данных'], 500);
        }
    }

    private function isDevCurrentUser(CurrentUser $currentUser): bool
    {
        return $this->telegramAuthConfig->appEnv === 'local'
            && $this->telegramAuthConfig->devAuthEnabled
            && $this->telegramAuthConfig->devUserId === $currentUser->telegramId;
    }

    private function timezoneOffsetFromRequest(Request $request): int
    {
        $params = $request->getQueryParams();
        $timezoneOffset = isset($params['tz_offset']) ? (int)$params['tz_offset'] : 0;

        if ($timezoneOffset < -840 || $timezoneOffset > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        return $timezoneOffset;
    }

}
