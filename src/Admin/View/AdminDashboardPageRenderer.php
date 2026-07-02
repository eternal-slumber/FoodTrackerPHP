<?php

declare(strict_types=1);

namespace App\Admin\View;

use App\Admin\Config\AdminConfig;
use App\View\ViewRenderer;

class AdminDashboardPageRenderer
{
    public function __construct(
        private readonly AdminConfig $adminConfig,
        private readonly AdminDashboardFormatter $formatter,
        private readonly ViewRenderer $viewRenderer
    ) {}

    /**
     * @param array{id:int, admin_login:?string, username:?string, role:string} $admin
     * @param array{users_entered_today:int, app_opened_today:int, meals_created_today:int, ai_requests_today:int, errors_today:int, active_users_total:int} $stats
     * @param list<array{id:int, user_id:?int, tg_id:?string, event_name:string, event_data:?string, ip_address:?string, user_agent:?string, created_at:string}> $userActivityEvents
     * @param list<array{date:string, label:string, unique_entries:int, visits:int}> $userActivityChart
     * @param list<array{id:int, tg_id:string}> $mealUsers
     * @param list<array{hour:int, label:string, meals:int}> $mealHourlyChart
     * @param list<array{id:int, user_id:int, tg_id:string, food_description:?string, calories:int, proteins:float, fats:float, carbs:float, total_weight:?int, created_at:string}> $mealLogs
     * @param array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string} $dayNavigation
     * @param list<array{id:int, user_id:?int, request_type:string, status:string, response_time_ms:?int, error_message:?string, created_at:string}> $aiRequests
     * @param list<array{date:string, label:string, requests:int}> $aiRequestsChart
     * @param array{scan:int, autocomplete:int, other:int} $aiRequestTypeStats
     * @param array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string} $weekNavigation
     */
    public function render(
        array $admin,
        array $stats,
        array $userActivityEvents,
        array $userActivityChart,
        array $mealUsers,
        array $mealHourlyChart,
        array $mealLogs,
        int $mealsSelectedDay,
        int $dayOffset,
        array $dayNavigation,
        ?int $selectedMealUserId,
        array $aiRequests,
        array $aiRequestsChart,
        array $aiRequestTypeStats,
        array $weekNavigation,
        array $systemLogPage,
        bool $databaseAvailable,
        bool $appAvailable,
        string $activePage,
        string $csrfToken
    ): string {
        $activePage = $this->normalizeActivePage($activePage);
        $replacements = $this->buildCommonReplacements(
            $admin,
            $stats,
            $userActivityEvents,
            $userActivityChart,
            $mealUsers,
            $mealHourlyChart,
            $mealLogs,
            $mealsSelectedDay,
            $dayOffset,
            $dayNavigation,
            $selectedMealUserId,
            $aiRequests,
            $aiRequestsChart,
            $aiRequestTypeStats,
            $weekNavigation,
            $systemLogPage,
            $databaseAvailable,
            $appAvailable,
            $activePage,
            $csrfToken
        );

        $pageContent = $this->viewRenderer->render($this->templateForPage($activePage), $replacements);
        $replacements['PAGE_CONTENT'] = $pageContent;

        return $this->viewRenderer->render('admin/dashboard/layout.html', $replacements);
    }

    /**
     * @param array{id:int, admin_login:?string, username:?string, role:string} $admin
     * @param array{users_entered_today:int, app_opened_today:int, meals_created_today:int, ai_requests_today:int, errors_today:int, active_users_total:int} $stats
     * @param list<array{id:int, user_id:?int, tg_id:?string, event_name:string, event_data:?string, ip_address:?string, user_agent:?string, created_at:string}> $userActivityEvents
     * @param list<array{date:string, label:string, unique_entries:int, visits:int}> $userActivityChart
     * @param list<array{id:int, tg_id:string}> $mealUsers
     * @param list<array{hour:int, label:string, meals:int}> $mealHourlyChart
     * @param list<array{id:int, user_id:int, tg_id:string, food_description:?string, calories:int, proteins:float, fats:float, carbs:float, total_weight:?int, created_at:string}> $mealLogs
     * @param array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string} $dayNavigation
     * @param list<array{id:int, user_id:?int, request_type:string, status:string, response_time_ms:?int, error_message:?string, created_at:string}> $aiRequests
     * @param list<array{date:string, label:string, requests:int}> $aiRequestsChart
     * @param array{scan:int, autocomplete:int, other:int} $aiRequestTypeStats
     * @param array{label:string, previous_url:string, next_url:string, current_url:string, next_class:string, next_aria_disabled:string} $weekNavigation
     * @return array<string, string>
     */
    private function buildCommonReplacements(
        array $admin,
        array $stats,
        array $userActivityEvents,
        array $userActivityChart,
        array $mealUsers,
        array $mealHourlyChart,
        array $mealLogs,
        int $mealsSelectedDay,
        int $dayOffset,
        array $dayNavigation,
        ?int $selectedMealUserId,
        array $aiRequests,
        array $aiRequestsChart,
        array $aiRequestTypeStats,
        array $weekNavigation,
        array $systemLogPage,
        bool $databaseAvailable,
        bool $appAvailable,
        string $activePage,
        string $csrfToken
    ): array {
        return [
            'ADMIN_PATH' => $this->escape($this->adminConfig->path),
            'ADMIN_PATH_JSON' => json_encode($this->adminConfig->path, JSON_THROW_ON_ERROR),
            'ADMIN_CSRF_TOKEN_JSON' => json_encode($csrfToken, JSON_THROW_ON_ERROR),
            'ADMIN_DASHBOARD_PATH' => $this->escape($this->adminConfig->path . '/dashboard'),
            'ADMIN_VISITS_PATH' => $this->escape($this->adminConfig->path . '/dashboard/user-activity'),
            'ADMIN_MEALS_PATH' => $this->escape($this->adminConfig->path . '/dashboard/meal-activity'),
            'ADMIN_AI_PATH' => $this->escape($this->adminConfig->path . '/dashboard/ai-activity'),
            'ADMIN_SYSTEM_LOGS_PATH' => $this->escape($this->adminConfig->path . '/dashboard/system-logs'),
            'PAGE_TITLE' => $this->escape($this->pageTitle($activePage)),
            'PAGE_DESCRIPTION' => $this->escape($this->pageDescription($activePage)),
            'DOCUMENT_TITLE' => $this->escape($this->documentTitle($activePage)),
            'OVERVIEW_ACTIVE_CLASS' => $activePage === 'overview' ? 'admin-is-active' : '',
            'VISITS_ACTIVE_CLASS' => $activePage === 'visits' ? 'admin-is-active' : '',
            'MEALS_ACTIVE_CLASS' => $activePage === 'meals' ? 'admin-is-active' : '',
            'AI_ACTIVE_CLASS' => $activePage === 'ai' ? 'admin-is-active' : '',
            'ERRORS_ACTIVE_CLASS' => $activePage === 'errors' ? 'admin-is-active' : '',
            'ADMIN_LOGIN' => $this->escape((string)($admin['admin_login'] ?? '')),
            'ROLE' => $this->escape($admin['role']),
            'USERS_ENTERED_TODAY' => (string)$stats['users_entered_today'],
            'APP_OPENED_TODAY' => (string)$stats['app_opened_today'],
            'MEALS_CREATED_TODAY' => (string)$stats['meals_created_today'],
            'MEALS_SELECTED_DAY' => (string)$mealsSelectedDay,
            'MEAL_USER_OPTIONS' => $this->formatter->renderMealUserOptions($mealUsers, $selectedMealUserId),
            'MEAL_ACTIVITY_ROWS' => $this->formatter->renderMealRows($mealLogs),
            'MEAL_HOURLY_CHART_JSON' => $this->formatter->jsonForHtml($mealHourlyChart),
            'MEAL_DAY_LABEL' => $this->escape($dayNavigation['label']),
            'MEAL_PREVIOUS_DAY_URL' => $this->escape($dayNavigation['previous_url']),
            'MEAL_NEXT_DAY_URL' => $this->escape($dayNavigation['next_url']),
            'MEAL_CURRENT_DAY_URL' => $this->escape($dayNavigation['current_url']),
            'MEAL_NEXT_DAY_CLASS' => $dayNavigation['next_class'],
            'MEAL_NEXT_DAY_ARIA_DISABLED' => $dayNavigation['next_aria_disabled'],
            'MEAL_DAY_OFFSET' => (string)$dayOffset,
            'AI_REQUESTS_TODAY' => (string)$stats['ai_requests_today'],
            'ERRORS_TODAY' => (string)$stats['errors_today'],
            'ACTIVE_USERS_TOTAL' => (string)$stats['active_users_total'],
            'USER_ACTIVITY_ROWS' => $this->formatter->renderUserActivityRows($userActivityEvents),
            'USER_ACTIVITY_CHART_JSON' => $this->formatter->jsonForHtml($userActivityChart),
            'AI_REQUEST_ROWS' => $this->formatter->renderAiRequestRows($aiRequests),
            'AI_REQUEST_CHART_JSON' => $this->formatter->jsonForHtml($aiRequestsChart),
            'AI_REQUEST_TYPES_JSON' => $this->formatter->jsonForHtml($this->formatter->formatAiRequestTypeChart($aiRequestTypeStats)),
            'AI_REQUESTS_SELECTED_WEEK' => (string)array_sum($aiRequestTypeStats),
            'AI_SCAN_REQUESTS_TODAY' => (string)$aiRequestTypeStats['scan'],
            'AI_AUTOCOMPLETE_REQUESTS_TODAY' => (string)$aiRequestTypeStats['autocomplete'],
            'AI_OTHER_REQUESTS_TODAY' => (string)$aiRequestTypeStats['other'],
            'WEEK_LABEL' => $this->escape($weekNavigation['label']),
            'PREVIOUS_WEEK_URL' => $this->escape($weekNavigation['previous_url']),
            'NEXT_WEEK_URL' => $this->escape($weekNavigation['next_url']),
            'CURRENT_WEEK_URL' => $this->escape($weekNavigation['current_url']),
            'NEXT_WEEK_CLASS' => $weekNavigation['next_class'],
            'NEXT_WEEK_ARIA_DISABLED' => $weekNavigation['next_aria_disabled'],
            'DATABASE_STATUS_CLASS' => $databaseAvailable ? 'admin-badge-ok' : 'admin-badge-danger',
            'DATABASE_STATUS_TEXT' => $databaseAvailable ? 'OK' : 'недоступна',
            'DATABASE_STATUS_NOTE' => $databaseAvailable
                ? 'Метрики читаются из MySQL'
                : 'MySQL недоступна, поэтому метрики временно не обновляются',
            'APP_STATUS_CLASS' => $appAvailable ? 'admin-badge-ok' : 'admin-badge-danger',
            'APP_STATUS_TEXT' => $appAvailable ? 'OK' : 'недоступно',
            'SYSTEM_LOG_ROWS' => $this->formatter->renderSystemLogRows($systemLogPage['items']),
            'SYSTEM_LOG_TOTAL' => (string)$systemLogPage['total'],
            'SYSTEM_LOG_CHANNEL_OPTIONS' => $this->formatter->renderSystemLogChannelOptions(
                $systemLogPage['channels'],
                $systemLogPage['filters']['channel']
            ),
            'SYSTEM_LOG_DATE' => $this->escape($systemLogPage['filters']['date']),
            'SYSTEM_LOG_LEVEL_ERRORS_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'errors'),
            'SYSTEM_LOG_LEVEL_CRITICAL_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'critical'),
            'SYSTEM_LOG_LEVEL_ERROR_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'error'),
            'SYSTEM_LOG_LEVEL_WARNING_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'warning'),
            'SYSTEM_LOG_LEVEL_INFO_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'info'),
            'SYSTEM_LOG_LEVEL_DEBUG_SELECTED' => $this->selected($systemLogPage['filters']['level'], 'debug'),
            'SYSTEM_LOG_LEVEL_ALL_SELECTED' => $this->selected($systemLogPage['filters']['level'], ''),
            'SYSTEM_LOG_PAGE_LABEL' => $this->escape($systemLogPage['pagination']['label']),
            'SYSTEM_LOG_PREVIOUS_URL' => $this->escape($systemLogPage['pagination']['previous_url']),
            'SYSTEM_LOG_NEXT_URL' => $this->escape($systemLogPage['pagination']['next_url']),
            'SYSTEM_LOG_PREVIOUS_CLASS' => $systemLogPage['pagination']['previous_class'],
            'SYSTEM_LOG_NEXT_CLASS' => $systemLogPage['pagination']['next_class'],
            'ERRORS_EMPTY_CLASS' => $stats['errors_today'] === 0 ? '' : 'admin-hidden',
            'ERRORS_PRESENT_CLASS' => $stats['errors_today'] > 0 ? '' : 'admin-hidden',
        ];
    }

    private function normalizeActivePage(string $activePage): string
    {
        return in_array($activePage, ['overview', 'visits', 'meals', 'ai', 'errors'], true) ? $activePage : 'overview';
    }

    private function templateForPage(string $activePage): string
    {
        return match ($activePage) {
            'visits' => 'admin/dashboard/pages/user-activity.html',
            'meals' => 'admin/dashboard/pages/meal-activity.html',
            'ai' => 'admin/dashboard/pages/ai-activity.html',
            'errors' => 'admin/dashboard/pages/system-logs.html',
            default => 'admin/dashboard/pages/overview.html',
        };
    }

    private function pageTitle(string $activePage): string
    {
        return match ($activePage) {
            'visits' => 'Активность пользователей',
            'meals' => 'Активность приёмов',
            'ai' => 'Активность ИИ',
            'errors' => 'Системные логи',
            default => 'Обзор сервиса',
        };
    }

    private function pageDescription(string $activePage): string
    {
        return match ($activePage) {
            'visits' => 'События входа пользователей в Mini App.',
            'meals' => 'Созданные приёмы пищи и базовая сводка по ним.',
            'ai' => 'AI-запросы, состояние обработки и базовая сводка.',
            'errors' => 'Ошибки и служебные события приложения без раскрытия чувствительного контекста.',
            default => 'Состояние FoodTracker и активность за сегодня.',
        };
    }

    private function documentTitle(string $activePage): string
    {
        return match ($activePage) {
            'visits' => 'FoodTracker · Активность пользователей',
            'meals' => 'FoodTracker · Активность приёмов',
            'ai' => 'FoodTracker · Активность ИИ',
            'errors' => 'FoodTracker · Системные логи',
            default => 'FoodTracker · Обзор',
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function selected(string $actual, string $expected): string
    {
        return $actual === $expected ? ' selected' : '';
    }
}
