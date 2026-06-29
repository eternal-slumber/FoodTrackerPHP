<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Core\DatabaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

class DashboardStatsService
{
    private const DASHBOARD_TIMEZONE = 'Europe/Moscow';

    public function __construct(private readonly DatabaseConnection $database) {}

    /**
     * @return array{users_entered_today:int, app_opened_today:int, meals_created_today:int, ai_requests_today:int, errors_today:int, active_users_total:int}
     */
    public function today(?DateTimeImmutable $now = null): array
    {
        [$startUtc, $endUtc] = $this->todayUtcRange($now);

        return [
            'users_entered_today' => $this->countUsersEnteredToday($startUtc, $endUtc),
            'app_opened_today' => $this->countAppOpenedToday($startUtc, $endUtc),
            'meals_created_today' => $this->count(
                'SELECT COUNT(*)
                 FROM meals
                 WHERE created_at >= :start_at
                   AND created_at < :end_at',
                $startUtc,
                $endUtc
            ),
            'ai_requests_today' => $this->count(
                'SELECT COUNT(*)
                 FROM ai_requests
                 WHERE created_at >= :start_at
                   AND created_at < :end_at',
                $startUtc,
                $endUtc
            ),
            'errors_today' => $this->count(
                "SELECT COUNT(*)
                 FROM system_logs
                 WHERE level IN ('error', 'critical')
                   AND created_at >= :start_at
                   AND created_at < :end_at",
                $startUtc,
                $endUtc
            ),
            'active_users_total' => $this->countAllUsers(),
        ];
    }

    /**
     * @return list<array{id:int, user_id:?int, tg_id:?string, event_name:string, event_data:?string, ip_address:?string, user_agent:?string, created_at:string}>
     */
    public function userActivityEvents(int $weekOffset = 0, ?DateTimeImmutable $now = null): array
    {
        [$startUtc, $endUtc] = $this->weekUtcRange($weekOffset, $now);
        $stmt = $this->db()->prepare(
            "SELECT
                ue.id,
                ue.user_id,
                u.tg_id,
                ue.event_name,
                ue.event_data,
                ue.ip_address,
                ue.user_agent,
                ue.created_at
             FROM user_events ue
             LEFT JOIN users u ON u.id = ue.user_id
             WHERE ue.event_name IN ('app_opened', 'user_registered')
               AND ue.created_at >= :start_at
               AND ue.created_at < :end_at
             ORDER BY ue.created_at DESC, ue.id DESC"
        );
        $stmt->execute([
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return list<array{date:string, label:string, unique_entries:int, visits:int}>
     */
    public function userActivityChart(int $weekOffset = 0, ?DateTimeImmutable $now = null): array
    {
        $localWeekStart = $this->localWeekStart($weekOffset, $now);

        $items = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $localDay = $localWeekStart->modify('+' . $offset . ' days');
            [$startUtc, $endUtc] = $this->localDayUtcRange($localDay);

            $items[] = [
                'date' => $localDay->format('Y-m-d'),
                'label' => $localDay->format('d.m'),
                'unique_entries' => $this->countUsersEnteredToday($startUtc, $endUtc),
                'visits' => $this->count(
                    "SELECT COUNT(*)
                     FROM user_events
                     WHERE event_name = 'app_opened'
                       AND created_at >= :start_at
                       AND created_at < :end_at",
                    $startUtc,
                    $endUtc
                ),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id:int, user_id:?int, request_type:string, status:string, response_time_ms:?int, error_message:?string, created_at:string}>
     */
    public function aiRequests(int $weekOffset = 0, int $limit = 80, ?DateTimeImmutable $now = null): array
    {
        $limit = max(1, min(200, $limit));
        [$startUtc, $endUtc] = $this->weekUtcRange($weekOffset, $now);
        $stmt = $this->db()->prepare(
            "SELECT id, user_id, request_type, status, response_time_ms, error_message, created_at
             FROM ai_requests
             WHERE created_at >= :start_at
               AND created_at < :end_at
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return list<array{date:string, label:string, requests:int}>
     */
    public function aiRequestsChart(int $weekOffset = 0, ?DateTimeImmutable $now = null): array
    {
        $localWeekStart = $this->localWeekStart($weekOffset, $now);

        $items = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $localDay = $localWeekStart->modify('+' . $offset . ' days');
            [$startUtc, $endUtc] = $this->localDayUtcRange($localDay);

            $items[] = [
                'date' => $localDay->format('Y-m-d'),
                'label' => $localDay->format('d.m'),
                'requests' => $this->count(
                    'SELECT COUNT(*)
                     FROM ai_requests
                     WHERE created_at >= :start_at
                       AND created_at < :end_at',
                    $startUtc,
                    $endUtc
                ),
            ];
        }

        return $items;
    }

    /**
     * @return array{scan:int, autocomplete:int, other:int}
     */
    public function aiRequestTypeStats(int $weekOffset = 0, ?DateTimeImmutable $now = null): array
    {
        [$startUtc, $endUtc] = $this->weekUtcRange($weekOffset, $now);
        $stmt = $this->db()->prepare(
            'SELECT request_type, COUNT(*) AS requests_count
             FROM ai_requests
             WHERE created_at >= :start_at
               AND created_at < :end_at
             GROUP BY request_type'
        );
        $stmt->execute([
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ]);

        $stats = [
            'scan' => 0,
            'autocomplete' => 0,
            'other' => 0,
        ];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $bucket = $this->aiRequestTypeBucket((string)$row['request_type']);
            $stats[$bucket] += (int)$row['requests_count'];
        }

        return $stats;
    }

    /**
     * @return array{label:string, start:string, end:string}
     */
    public function weekInfo(int $weekOffset = 0, ?DateTimeImmutable $now = null): array
    {
        $localStart = $this->localWeekStart($weekOffset, $now);
        $localEnd = $localStart->modify('+6 days');

        return [
            'label' => $localStart->format('d.m') . ' - ' . $localEnd->format('d.m'),
            'start' => $localStart->format('Y-m-d'),
            'end' => $localEnd->format('Y-m-d'),
        ];
    }

    /**
     * @return array{total:int, weekly_average:float}
     */
    public function aiRequestSummary(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone(self::DASHBOARD_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localEnd = $now->setTimezone($timezone);
        $localStart = $localEnd->modify('-28 days');

        $startUtc = $localStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $localEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $total = $this->count(
            'SELECT COUNT(*)
             FROM ai_requests
             WHERE created_at >= :start_at
               AND created_at < :end_at',
            $startUtc,
            $endUtc
        );

        return [
            'total' => $total,
            'weekly_average' => round($total / 4, 1),
        ];
    }

    /**
     * @return list<array{id:int, tg_id:string}>
     */
    public function mealUsers(): array
    {
        $stmt = $this->db()->query(
            'SELECT DISTINCT u.id, u.tg_id
             FROM users u
             INNER JOIN meals m ON m.user_id = u.id
             ORDER BY u.id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }

    public function mealsCreatedForDay(int $dayOffset = 0, ?int $userId = null, ?DateTimeImmutable $now = null): int
    {
        [$startUtc, $endUtc] = $this->dayUtcRange($dayOffset, $now);

        return $this->countMeals($startUtc, $endUtc, $userId);
    }

    /**
     * @return list<array{hour:int, label:string, meals:int}>
     */
    public function mealHourlyChart(int $dayOffset = 0, ?int $userId = null, ?DateTimeImmutable $now = null): array
    {
        $localDay = $this->localDayStart($dayOffset, $now);
        $items = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $localHour = $localDay->modify('+' . $hour . ' hours');
            $localNextHour = $localHour->modify('+1 hour');
            $startUtc = $localHour->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $endUtc = $localNextHour->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

            $items[] = [
                'hour' => $hour,
                'label' => str_pad((string)$hour, 2, '0', STR_PAD_LEFT) . ':00',
                'meals' => $this->countMeals($startUtc, $endUtc, $userId),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id:int, user_id:int, tg_id:string, food_description:?string, calories:int, proteins:float, fats:float, carbs:float, total_weight:?int, created_at:string}>
     */
    public function mealLogs(int $dayOffset = 0, ?int $userId = null, int $limit = 120, ?DateTimeImmutable $now = null): array
    {
        $limit = max(1, min(200, $limit));
        [$startUtc, $endUtc] = $this->dayUtcRange($dayOffset, $now);
        $userCondition = $userId !== null ? 'AND m.user_id = :user_id' : '';
        $stmt = $this->db()->prepare(
            "SELECT
                m.id,
                m.user_id,
                u.tg_id,
                m.food_description,
                m.calories,
                m.proteins,
                m.fats,
                m.carbs,
                m.total_weight,
                m.created_at
             FROM meals m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.created_at >= :start_at
               AND m.created_at < :end_at
               {$userCondition}
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT {$limit}"
        );

        $params = [
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ];
        if ($userId !== null) {
            $params['user_id'] = $userId;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @return array{label:string, date:string}
     */
    public function dayInfo(int $dayOffset = 0, ?DateTimeImmutable $now = null): array
    {
        $localDay = $this->localDayStart($dayOffset, $now);

        return [
            'label' => $localDay->format('d.m.Y'),
            'date' => $localDay->format('Y-m-d'),
        ];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function todayUtcRange(?DateTimeImmutable $now): array
    {
        $timezone = new DateTimeZone(self::DASHBOARD_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localStart = $now->setTimezone($timezone)->setTime(0, 0);

        return $this->localDayUtcRange($localStart);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function localDayUtcRange(DateTimeImmutable $localStart): array
    {
        $localEnd = $localStart->modify('+1 day');

        return [
            $localStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $localEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function dayUtcRange(int $dayOffset, ?DateTimeImmutable $now): array
    {
        return $this->localDayUtcRange($this->localDayStart($dayOffset, $now));
    }

    private function localDayStart(int $dayOffset, ?DateTimeImmutable $now): DateTimeImmutable
    {
        $dayOffset = max(0, $dayOffset);
        $timezone = new DateTimeZone(self::DASHBOARD_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now
            ->setTimezone($timezone)
            ->setTime(0, 0)
            ->modify('-' . $dayOffset . ' days');
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function weekUtcRange(int $weekOffset, ?DateTimeImmutable $now): array
    {
        $localStart = $this->localWeekStart($weekOffset, $now);
        $localEnd = $localStart->modify('+7 days');

        return [
            $localStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $localEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }

    private function localWeekStart(int $weekOffset, ?DateTimeImmutable $now): DateTimeImmutable
    {
        $weekOffset = max(0, $weekOffset);
        $timezone = new DateTimeZone(self::DASHBOARD_TIMEZONE);
        $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localToday = $now->setTimezone($timezone)->setTime(0, 0);
        $weekDay = (int)$localToday->format('N');

        return $localToday
            ->modify('-' . ($weekDay - 1) . ' days')
            ->modify('-' . $weekOffset . ' weeks');
    }

    private function count(string $sql, string $startUtc, string $endUtc): int
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ]);

        return (int)$stmt->fetchColumn();
    }

    private function countAllUsers(): int
    {
        return (int)$this->db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function countMeals(string $startUtc, string $endUtc, ?int $userId): int
    {
        $userCondition = $userId !== null ? 'AND user_id = :user_id' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*)
             FROM meals
             WHERE created_at >= :start_at
               AND created_at < :end_at
               {$userCondition}"
        );
        $params = [
            'start_at' => $startUtc,
            'end_at' => $endUtc,
        ];
        if ($userId !== null) {
            $params['user_id'] = $userId;
        }
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    private function countUsersEnteredToday(string $startUtc, string $endUtc): int
    {
        $registeredUsers = $this->count(
            "SELECT COUNT(DISTINCT user_id)
             FROM user_events
             WHERE event_name = 'app_opened'
               AND user_id IS NOT NULL
               AND created_at >= :start_at
               AND created_at < :end_at",
            $startUtc,
            $endUtc
        );

        return $registeredUsers + $this->count(
            "SELECT COUNT(DISTINCT event_data)
             FROM user_events
             WHERE event_name = 'app_opened'
               AND user_id IS NULL
               AND event_data LIKE '%\"dev_telegram_id\"%'
               AND created_at >= :start_at
               AND created_at < :end_at",
            $startUtc,
            $endUtc
        );
    }

    private function countAppOpenedToday(string $startUtc, string $endUtc): int
    {
        return $this->count(
            "SELECT COUNT(*)
             FROM user_events
             WHERE event_name = 'app_opened'
               AND created_at >= :start_at
               AND created_at < :end_at",
            $startUtc,
            $endUtc
        );
    }

    private function aiRequestTypeBucket(string $requestType): string
    {
        $normalizedType = strtolower($requestType);

        if (str_contains($normalizedType, 'analyze') || str_contains($normalizedType, 'scan')) {
            return 'scan';
        }

        if (str_contains($normalizedType, 'nutrient') || str_contains($normalizedType, 'auto')) {
            return 'autocomplete';
        }

        return 'other';
    }

    private function db(): PDO
    {
        return $this->database->get();
    }
}
