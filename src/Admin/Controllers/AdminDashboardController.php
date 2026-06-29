<?php

declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\Auth\AdminSession;
use App\Admin\Config\AdminConfig;
use App\Admin\Repositories\AdminUserRepository;
use App\Admin\Services\AppHealthService;
use App\Admin\Services\DashboardStatsService;
use App\Admin\View\AdminDashboardPageRenderer;
use App\Http\ResponseResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class AdminDashboardController
{
    public function __construct(
        private readonly AdminConfig $adminConfig,
        private readonly AdminSession $adminSession,
        private readonly AdminUserRepository $adminUsers,
        private readonly AppHealthService $appHealth,
        private readonly DashboardStatsService $dashboardStats,
        private readonly AdminDashboardPageRenderer $pageRenderer
    ) {}

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->dashboardPage($request, $response, 'overview');
    }

    public function visits(Request $request, Response $response): Response
    {
        return $this->redirect($response, $this->adminConfig->path . '/dashboard/user-activity');
    }

    public function userActivity(Request $request, Response $response): Response
    {
        return $this->dashboardPage($request, $response, 'visits');
    }

    public function mealActivity(Request $request, Response $response): Response
    {
        return $this->dashboardPage($request, $response, 'meals');
    }

    public function aiActivity(Request $request, Response $response): Response
    {
        return $this->dashboardPage($request, $response, 'ai');
    }

    private function dashboardPage(Request $request, Response $response, string $activePage): Response
    {
        $adminId = $this->adminSession->currentAdminId();
        if ($adminId === null) {
            return $this->redirect($response, $this->adminConfig->path);
        }

        $weekOffset = $this->weekOffset($request);
        $dayOffset = $this->dayOffset($request);
        $selectedMealUserId = $this->selectedMealUserId($request);

        try {
            $admin = $this->adminUsers->findActiveById($adminId);
        } catch (Throwable) {
            return ResponseResponder::html($response, $this->renderDashboard(
                $this->fallbackAdmin($adminId),
                $this->emptyDashboardData($activePage, $weekOffset, $dayOffset, $selectedMealUserId),
                false,
                $this->appHealth->isAvailable(),
                $activePage
            ));
        }

        if ($admin === null) {
            $this->adminSession->destroy();

            return $this->redirect($response, $this->adminConfig->path);
        }

        try {
            $dashboardData = $this->loadDashboardData($activePage, $weekOffset, $dayOffset, $selectedMealUserId);
            $databaseAvailable = true;
        } catch (Throwable) {
            $dashboardData = $this->emptyDashboardData($activePage, $weekOffset, $dayOffset, $selectedMealUserId);
            $databaseAvailable = false;
        }

        return ResponseResponder::html($response, $this->renderDashboard(
            $admin,
            $dashboardData,
            $databaseAvailable,
            $this->appHealth->isAvailable(),
            $activePage
        ));
    }

    /** @return array<string, mixed> */
    private function loadDashboardData(
        string $activePage,
        int $weekOffset,
        int $dayOffset,
        ?int $selectedMealUserId
    ): array {
        return [
            'stats' => $this->dashboardStats->today(),
            'user_activity_events' => $this->dashboardStats->userActivityEvents($weekOffset),
            'user_activity_chart' => $this->dashboardStats->userActivityChart($weekOffset),
            'meal_users' => $this->dashboardStats->mealUsers(),
            'meal_hourly_chart' => $this->dashboardStats->mealHourlyChart($dayOffset, $selectedMealUserId),
            'meal_logs' => $this->dashboardStats->mealLogs($dayOffset, $selectedMealUserId),
            'meals_selected_day' => $this->dashboardStats->mealsCreatedForDay($dayOffset, $selectedMealUserId),
            'day_offset' => $dayOffset,
            'day_navigation' => $this->dayNavigation($dayOffset, $selectedMealUserId),
            'selected_meal_user_id' => $selectedMealUserId,
            'ai_requests' => $this->dashboardStats->aiRequests($weekOffset),
            'ai_requests_chart' => $this->dashboardStats->aiRequestsChart($weekOffset),
            'ai_request_type_stats' => $this->dashboardStats->aiRequestTypeStats($weekOffset),
            'week_navigation' => $this->weekNavigation($activePage, $weekOffset),
        ];
    }

    /**
     * @param array{id:int, admin_login:?string, username:?string, role:string} $admin
     * @param array<string, mixed> $dashboardData
     */
    private function renderDashboard(
        array $admin,
        array $dashboardData,
        bool $databaseAvailable,
        bool $appAvailable,
        string $activePage
    ): string {
        return $this->pageRenderer->render(
            $admin,
            $dashboardData['stats'],
            $dashboardData['user_activity_events'],
            $dashboardData['user_activity_chart'],
            $dashboardData['meal_users'],
            $dashboardData['meal_hourly_chart'],
            $dashboardData['meal_logs'],
            $dashboardData['meals_selected_day'],
            $dashboardData['day_offset'],
            $dashboardData['day_navigation'],
            $dashboardData['selected_meal_user_id'],
            $dashboardData['ai_requests'],
            $dashboardData['ai_requests_chart'],
            $dashboardData['ai_request_type_stats'],
            $dashboardData['week_navigation'],
            $databaseAvailable,
            $appAvailable,
            $activePage
        );
    }

    /**
     * @return array{id:int, admin_login:string, username:null, role:string}
     */
    private function fallbackAdmin(int $adminId): array
    {
        return [
            'id' => $adminId,
            'admin_login' => 'session #' . $adminId,
            'username' => null,
            'role' => 'admin',
        ];
    }

    /** @return array<string, mixed> */
    private function emptyDashboardData(
        string $activePage,
        int $weekOffset,
        int $dayOffset,
        ?int $selectedMealUserId
    ): array {
        return [
            'stats' => [
                'users_entered_today' => 0,
                'app_opened_today' => 0,
                'meals_created_today' => 0,
                'ai_requests_today' => 0,
                'errors_today' => 0,
                'active_users_total' => 0,
            ],
            'user_activity_events' => [],
            'user_activity_chart' => [],
            'meal_users' => [],
            'meal_hourly_chart' => [],
            'meal_logs' => [],
            'meals_selected_day' => 0,
            'day_offset' => $dayOffset,
            'day_navigation' => $this->dayNavigation($dayOffset, $selectedMealUserId),
            'selected_meal_user_id' => $selectedMealUserId,
            'ai_requests' => [],
            'ai_requests_chart' => [],
            'ai_request_type_stats' => [
                'scan' => 0,
                'autocomplete' => 0,
                'other' => 0,
            ],
            'week_navigation' => $this->weekNavigation($activePage, $weekOffset),
        ];
    }

    private function weekOffset(Request $request): int
    {
        $queryParams = $request->getQueryParams();
        $week = (int)($queryParams['week'] ?? 0);

        return max(0, $week);
    }

    private function dayOffset(Request $request): int
    {
        $queryParams = $request->getQueryParams();
        $day = (int)($queryParams['day'] ?? 0);

        return max(0, $day);
    }

    private function selectedMealUserId(Request $request): ?int
    {
        $queryParams = $request->getQueryParams();
        $userId = (int)($queryParams['user_id'] ?? 0);

        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string}
     */
    private function weekNavigation(string $activePage, int $weekOffset): array
    {
        $weekInfo = $this->dashboardStats->weekInfo($weekOffset);
        $basePath = $this->pagePath($activePage);

        return [
            'label' => $weekInfo['label'],
            'previous_url' => $basePath . '?week=' . ($weekOffset + 1),
            'next_url' => $weekOffset > 0 ? $basePath . '?week=' . ($weekOffset - 1) : $basePath,
            'current_url' => $basePath,
            'next_class' => $weekOffset > 0 ? '' : 'admin-is-disabled',
            'next_aria_disabled' => $weekOffset > 0 ? 'false' : 'true',
        ];
    }

    /**
     * @return array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string}
     */
    private function dayNavigation(int $dayOffset, ?int $userId): array
    {
        $dayInfo = $this->dashboardStats->dayInfo($dayOffset);
        $basePath = $this->pagePath('meals');

        return [
            'label' => $dayInfo['label'],
            'previous_url' => $this->pathWithQuery($basePath, [
                'day' => $dayOffset + 1,
                'user_id' => $userId,
            ]),
            'next_url' => $this->pathWithQuery($basePath, [
                'day' => max(0, $dayOffset - 1),
                'user_id' => $userId,
            ]),
            'current_url' => $this->pathWithQuery($basePath, [
                'user_id' => $userId,
            ]),
            'next_class' => $dayOffset > 0 ? '' : 'admin-is-disabled',
            'next_aria_disabled' => $dayOffset > 0 ? 'false' : 'true',
        ];
    }

    /**
     * @param array<string, int|null> $params
     */
    private function pathWithQuery(string $path, array $params): string
    {
        $filteredParams = array_filter(
            $params,
            static fn(?int $value): bool => $value !== null && $value > 0
        );

        if ($filteredParams === []) {
            return $path;
        }

        return $path . '?' . http_build_query($filteredParams);
    }

    private function pagePath(string $activePage): string
    {
        return match ($activePage) {
            'visits' => $this->adminConfig->path . '/dashboard/user-activity',
            'meals' => $this->adminConfig->path . '/dashboard/meal-activity',
            'ai' => $this->adminConfig->path . '/dashboard/ai-activity',
            default => $this->adminConfig->path . '/dashboard',
        };
    }

    private function redirect(Response $response, string $location): Response
    {
        return $response
            ->withHeader('Location', $location)
            ->withStatus(302);
    }
}
