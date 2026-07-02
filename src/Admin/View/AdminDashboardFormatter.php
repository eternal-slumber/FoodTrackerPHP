<?php

declare(strict_types=1);

namespace App\Admin\View;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class AdminDashboardFormatter
{
    /**
     * @param list<array{id:int, level:string, channel:string, message:string, exception_class:?string, trace_id:?string, created_at:string}> $logs
     */
    public function renderSystemLogRows(array $logs): string
    {
        if ($logs === []) {
            return '<div class="admin-placeholder-row admin-system-log-row"><span>-</span><span>-</span><span>-</span><span>Записей по выбранным фильтрам нет</span><span>-</span><span>-</span></div>';
        }

        $rows = [];
        foreach ($logs as $log) {
            $level = strtolower((string)$log['level']);
            $levelClass = in_array($level, ['critical', 'error', 'warning', 'info', 'debug'], true)
                ? 'admin-log-level-' . $level
                : 'admin-log-level-default';

            $rows[] = sprintf(
                '<div class="admin-placeholder-row admin-system-log-row"><span>%s</span><span class="admin-log-level %s">%s</span><span>%s</span><span>%s</span><span>%s</span><span>%s</span></div>',
                $this->escape($this->formatMoscowTime((string)$log['created_at'])),
                $levelClass,
                $this->escape($this->formatSystemLogLevel($level)),
                $this->escape((string)$log['channel']),
                $this->escape($this->truncate((string)$log['message'], 240)),
                $this->escape($this->shortClassName($log['exception_class'])),
                $this->escape($log['trace_id'] !== null ? (string)$log['trace_id'] : '-')
            );
        }

        return implode('', $rows);
    }

    /** @param list<string> $channels */
    public function renderSystemLogChannelOptions(array $channels, string $selectedChannel): string
    {
        $options = ['<option value="">Все каналы</option>'];

        foreach ($channels as $channel) {
            $selected = $channel === $selectedChannel ? ' selected' : '';
            $options[] = sprintf(
                '<option value="%s"%s>%s</option>',
                $this->escape($channel),
                $selected,
                $this->escape($channel)
            );
        }

        return implode('', $options);
    }

    /**
     * @param list<array{id:int, user_id:?int, tg_id:?string, event_name:string, event_data:?string, ip_address:?string, user_agent:?string, created_at:string}> $events
     */
    public function renderUserActivityRows(array $events): string
    {
        if ($events === []) {
            return '<div class="admin-placeholder-row"><span>-</span><span>Данных пока нет</span><span>-</span><span>mini app</span></div>';
        }

        $rows = [];
        foreach ($events as $event) {
            $rows[] = sprintf(
                '<div class="admin-placeholder-row"><span>%s</span><span>%s</span><span>%s</span><span>mini app</span></div>',
                $this->escape($this->formatMoscowTime((string)$event['created_at'])),
                $this->escape($this->formatActivityUser($event)),
                $this->escape($this->formatActivityEventName($event['event_name']))
            );
        }

        return implode('', $rows);
    }

    /**
     * @param list<array{id:int, user_id:?int, request_type:string, status:string, response_time_ms:?int, error_message:?string, created_at:string}> $requests
     */
    public function renderAiRequestRows(array $requests): string
    {
        if ($requests === []) {
            return '<div class="admin-placeholder-row admin-ai-request-row"><span>-</span><span>Запросов пока нет</span><span>-</span><span>-</span><span>-</span></div>';
        }

        $rows = [];
        foreach ($requests as $request) {
            $rows[] = sprintf(
                '<div class="admin-placeholder-row admin-ai-request-row"><span>%s</span><span>%s</span><span>%s</span><span>%s</span><span>%s</span></div>',
                $this->escape($this->formatMoscowTime((string)$request['created_at'])),
                $this->escape($this->formatAiRequestType((string)$request['request_type'])),
                $this->escape($this->formatAiRequestStatus((string)$request['status'])),
                $this->escape($this->formatResponseTime($request['response_time_ms'])),
                $this->escape($this->formatAiRequestError($request['error_message']))
            );
        }

        return implode('', $rows);
    }

    /**
     * @param list<array{id:int, tg_id:string}> $users
     */
    public function renderMealUserOptions(array $users, ?int $selectedUserId): string
    {
        $options = ['<option value="">Все пользователи</option>'];

        foreach ($users as $user) {
            $userId = (int)$user['id'];
            $selected = $selectedUserId === $userId ? ' selected' : '';
            $label = 'user #' . $userId . ' / tg ' . (string)$user['tg_id'];

            $options[] = sprintf(
                '<option value="%d"%s>%s</option>',
                $userId,
                $selected,
                $this->escape($label)
            );
        }

        return implode('', $options);
    }

    /**
     * @param list<array{id:int, user_id:int, tg_id:string, food_description:?string, calories:int, proteins:float, fats:float, carbs:float, total_weight:?int, created_at:string}> $meals
     */
    public function renderMealRows(array $meals): string
    {
        if ($meals === []) {
            return '<div class="admin-placeholder-row admin-meal-row"><span>-</span><span>Приёмов за выбранный день нет</span><span>-</span><span>-</span><span>-</span></div>';
        }

        $rows = [];
        foreach ($meals as $meal) {
            $rows[] = sprintf(
                '<div class="admin-placeholder-row admin-meal-row"><span>%s</span><span>%s</span><span>%s</span><span>%s</span><span>%s</span></div>',
                $this->escape($this->formatMoscowTime((string)$meal['created_at'])),
                $this->escape('user #' . (int)$meal['user_id'] . ' / tg ' . (string)$meal['tg_id']),
                $this->escape($this->formatMealDescription($meal['food_description'])),
                $this->escape((string)(int)$meal['calories'] . ' ккал'),
                $this->escape($this->formatMealMacros($meal))
            );
        }

        return implode('', $rows);
    }

    /**
     * @param array{scan:int, autocomplete:int, other:int} $typeStats
     * @return list<array{label:string, value:int}>
     */
    public function formatAiRequestTypeChart(array $typeStats): array
    {
        return [
            ['label' => 'Сканирования', 'value' => $typeStats['scan']],
            ['label' => 'Автозаполнения', 'value' => $typeStats['autocomplete']],
            ['label' => 'Прочее', 'value' => $typeStats['other']],
        ];
    }

    public function jsonForHtml(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    private function formatMoscowTime(string $utcTime): string
    {
        try {
            $date = new DateTimeImmutable($utcTime, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return $utcTime;
        }

        return $date
            ->setTimezone(new DateTimeZone('Europe/Moscow'))
            ->format('d.m.Y H:i');
    }

    private function formatSystemLogLevel(string $level): string
    {
        return match ($level) {
            'critical' => 'Critical',
            'error' => 'Ошибка',
            'warning' => 'Предупреждение',
            'info' => 'Информация',
            'debug' => 'Debug',
            default => $level,
        };
    }

    private function shortClassName(?string $className): string
    {
        if ($className === null || $className === '') {
            return '-';
        }

        $parts = explode('\\', $className);

        return (string)end($parts);
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;
    }

    private function formatActivityEventName(string $eventName): string
    {
        return match ($eventName) {
            'app_opened' => 'app.opened',
            'user_registered' => 'registration',
            default => str_replace('_', '.', $eventName),
        };
    }

    private function formatAiRequestType(string $requestType): string
    {
        return match ($requestType) {
            'analyze' => 'Сканирование',
            'getProductNutrients' => 'Автозаполнение',
            'recommendMeal' => 'Рекомендация',
            default => $requestType,
        };
    }

    private function formatAiRequestStatus(string $status): string
    {
        return match ($status) {
            'success' => 'Успешно',
            'error' => 'Ошибка',
            default => $status,
        };
    }

    private function formatMealDescription(mixed $description): string
    {
        $value = trim((string)($description ?? ''));

        if ($value === '') {
            return 'без описания';
        }

        return strlen($value) > 80 ? substr($value, 0, 80) . '...' : $value;
    }

    /**
     * @param array{proteins:float, fats:float, carbs:float, total_weight:?int} $meal
     */
    private function formatMealMacros(array $meal): string
    {
        $macros = sprintf(
            'Б %.1f / Ж %.1f / У %.1f',
            (float)$meal['proteins'],
            (float)$meal['fats'],
            (float)$meal['carbs']
        );

        if ($meal['total_weight'] !== null) {
            return $macros . ' / ' . (int)$meal['total_weight'] . ' г';
        }

        return $macros;
    }

    private function formatResponseTime(mixed $responseTimeMs): string
    {
        if ($responseTimeMs === null || $responseTimeMs === '') {
            return '-';
        }

        return (string)(int)$responseTimeMs . ' мс';
    }

    private function formatAiRequestError(mixed $errorMessage): string
    {
        $message = trim((string)($errorMessage ?? ''));

        if ($message === '') {
            return '-';
        }

        return strlen($message) > 80 ? substr($message, 0, 80) . '...' : $message;
    }

    /**
     * @param array{user_id:?int, tg_id:?string, event_data:?string} $event
     */
    private function formatActivityUser(array $event): string
    {
        if ($event['user_id'] !== null) {
            $label = 'user #' . (int)$event['user_id'];

            return $event['tg_id'] !== null ? $label . ' / tg ' . $event['tg_id'] : $label;
        }

        $eventData = $this->decodeEventData($event['event_data']);
        if (isset($eventData['dev_telegram_id'])) {
            return 'dev tg ' . (string)$eventData['dev_telegram_id'];
        }

        return 'незарегистрированный пользователь';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeEventData(?string $eventData): array
    {
        if ($eventData === null || $eventData === '') {
            return [];
        }

        try {
            $decoded = json_decode($eventData, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
