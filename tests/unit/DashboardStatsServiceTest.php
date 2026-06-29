<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Services\DashboardStatsService;
use App\Core\DatabaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    public function testCountsTodayMetricsUsingMoscowDayRange(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec("INSERT INTO users (id) VALUES (1), (2), (3)");
        $db->exec(
            "INSERT INTO user_events (user_id, event_name, created_at) VALUES
             (1, 'app_opened', '2026-06-16 20:59:59'),
             (1, 'app_opened', '2026-06-16 21:00:00'),
             (1, 'app_opened', '2026-06-17 08:00:00'),
             (2, 'app_opened', '2026-06-17 20:59:59'),
             (3, 'meal_created', '2026-06-17 10:00:00'),
             (NULL, 'app_opened', '2026-06-17 10:00:00'),
             (3, 'app_opened', '2026-06-17 21:00:00')"
        );
        $db->exec(
            "INSERT INTO meals (created_at) VALUES
             ('2026-06-16 21:00:00'),
             ('2026-06-17 12:00:00'),
             ('2026-06-17 21:00:00')"
        );
        $db->exec(
            "INSERT INTO ai_requests (created_at) VALUES
             ('2026-06-17 01:00:00'),
             ('2026-06-17 21:00:00')"
        );
        $db->exec(
            "INSERT INTO system_logs (level, created_at) VALUES
             ('error', '2026-06-17 01:00:00'),
             ('critical', '2026-06-17 12:00:00'),
             ('warning', '2026-06-17 12:30:00'),
             ('error', '2026-06-17 21:00:00')"
        );

        $stats = $service->today(new DateTimeImmutable('2026-06-17 12:00:00', new DateTimeZone('UTC')));

        $this->assertSame(2, $stats['users_entered_today']);
        $this->assertSame(4, $stats['app_opened_today']);
        $this->assertSame(2, $stats['meals_created_today']);
        $this->assertSame(1, $stats['ai_requests_today']);
        $this->assertSame(2, $stats['errors_today']);
        $this->assertSame(3, $stats['active_users_total']);
    }

    public function testLocalModeCountsDevMockVisitsWithoutRegisteredUser(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec("INSERT INTO users (id) VALUES (1)");
        $db->exec(
            "INSERT INTO user_events (user_id, event_name, event_data, created_at) VALUES
             (1, 'app_opened', NULL, '2026-06-17 10:00:00'),
             (NULL, 'app_opened', '{\"dev\":true,\"dev_telegram_id\":200002}', '2026-06-17 10:05:00'),
             (NULL, 'app_opened', '{\"dev\":true,\"dev_telegram_id\":200002}', '2026-06-17 10:10:00')"
        );

        $stats = $service->today(new DateTimeImmutable('2026-06-17 12:00:00', new DateTimeZone('UTC')));

        $this->assertSame(2, $stats['users_entered_today']);
        $this->assertSame(3, $stats['app_opened_today']);
    }

    public function testReturnsUserActivityEventsNewestFirst(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec("INSERT INTO users (id, tg_id) VALUES (1, 900001)");
        $db->exec(
            "INSERT INTO user_events (id, user_id, event_name, event_data, ip_address, user_agent, created_at) VALUES
             (1, 1, 'app_opened', NULL, '127.0.0.1', 'Browser A', '2026-06-17 10:00:00'),
             (2, NULL, 'app_opened', '{\"dev\":true,\"dev_telegram_id\":200002}', NULL, NULL, '2026-06-17 10:05:00'),
             (3, 1, 'meal_created', NULL, NULL, NULL, '2026-06-17 10:10:00'),
             (4, 1, 'user_registered', '{\"source\":\"mini_app\"}', NULL, NULL, '2026-06-17 10:15:00')"
        );

        $events = $service->userActivityEvents(0, new DateTimeImmutable('2026-06-17 12:00:00', new DateTimeZone('UTC')));

        $this->assertCount(3, $events);
        $this->assertSame(4, $events[0]['id']);
        $this->assertSame('user_registered', $events[0]['event_name']);
        $this->assertSame(2, $events[1]['id']);
        $this->assertSame(1, $events[2]['id']);
        $this->assertSame(900001, $events[2]['tg_id']);
        $this->assertSame('127.0.0.1', $events[2]['ip_address']);
        $this->assertSame('Browser A', $events[2]['user_agent']);
    }

    public function testBuildsUserActivityChartForMoscowDays(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec("INSERT INTO users (id, tg_id) VALUES (1, 900001), (2, 900002)");
        $db->exec(
            "INSERT INTO user_events (user_id, event_name, event_data, created_at) VALUES
             (1, 'app_opened', NULL, '2026-06-15 21:30:00'),
             (1, 'app_opened', NULL, '2026-06-16 08:00:00'),
             (2, 'app_opened', NULL, '2026-06-16 10:00:00'),
             (NULL, 'app_opened', '{\"dev\":true,\"dev_telegram_id\":200002}', '2026-06-16 10:30:00'),
             (NULL, 'app_opened', '{\"dev\":true,\"dev_telegram_id\":200002}', '2026-06-16 11:30:00')"
        );

        $chart = $service->userActivityChart(0, new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC')));

        $this->assertCount(7, $chart);
        $this->assertSame('15.06', $chart[0]['label']);
        $this->assertSame(0, $chart[0]['unique_entries']);
        $this->assertSame(0, $chart[0]['visits']);
        $this->assertSame('16.06', $chart[1]['label']);
        $this->assertSame(3, $chart[1]['unique_entries']);
        $this->assertSame(5, $chart[1]['visits']);
    }

    public function testBuildsAiRequestStatsForMoscowDays(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec(
            "INSERT INTO ai_requests (id, request_type, status, response_time_ms, error_message, created_at) VALUES
             (1, 'analyze', 'success', 1200, NULL, '2026-06-15 21:30:00'),
             (2, 'getProductNutrients', 'success', 800, NULL, '2026-06-16 08:00:00'),
             (3, 'recommendMeal', 'error', 300, 'HTTP 500', '2026-06-16 10:00:00'),
             (4, 'analyze', 'success', 1400, NULL, '2026-06-16 21:00:00')"
        );

        $chart = $service->aiRequestsChart(0, new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC')));
        $typeStats = $service->aiRequestTypeStats(0, new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC')));
        $summary = $service->aiRequestSummary(new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC')));
        $requests = $service->aiRequests(0, 80, new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC')));

        $this->assertCount(7, $chart);
        $this->assertSame('15.06', $chart[0]['label']);
        $this->assertSame(0, $chart[0]['requests']);
        $this->assertSame('16.06', $chart[1]['label']);
        $this->assertSame(3, $chart[1]['requests']);
        $this->assertSame('17.06', $chart[2]['label']);
        $this->assertSame(1, $chart[2]['requests']);
        $this->assertSame(['scan' => 2, 'autocomplete' => 1, 'other' => 1], $typeStats);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(0.8, $summary['weekly_average']);
        $this->assertSame(4, $requests[0]['id']);
        $this->assertSame(3, $requests[1]['id']);
    }

    public function testBuildsMealActivityForSelectedMoscowDayAndUser(): void
    {
        $db = $this->createDatabase();
        $service = new DashboardStatsService(new FakeDashboardStatsDatabaseConnection($db));

        $db->exec("INSERT INTO users (id, tg_id) VALUES (1, 900001), (2, 900002)");
        $db->exec(
            "INSERT INTO meals (id, user_id, food_description, calories, proteins, fats, carbs, total_weight, created_at) VALUES
             (1, 1, 'Завтрак', 420, 20.5, 12.0, 48.0, 300, '2026-06-16 06:10:00'),
             (2, 1, 'Обед', 650, 35.0, 18.0, 70.0, 450, '2026-06-16 10:20:00'),
             (3, 2, 'Перекус', 180, 8.0, 6.0, 22.0, 120, '2026-06-16 10:40:00'),
             (4, 1, 'Поздний ужин', 500, 24.0, 14.0, 60.0, 350, '2026-06-16 21:10:00')"
        );

        $now = new DateTimeImmutable('2026-06-16 12:00:00', new DateTimeZone('UTC'));
        $chart = $service->mealHourlyChart(0, 1, $now);
        $logs = $service->mealLogs(0, 1, 120, $now);
        $users = $service->mealUsers();

        $this->assertCount(24, $chart);
        $this->assertSame('09:00', $chart[9]['label']);
        $this->assertSame(1, $chart[9]['meals']);
        $this->assertSame('13:00', $chart[13]['label']);
        $this->assertSame(1, $chart[13]['meals']);
        $this->assertSame(2, $service->mealsCreatedForDay(0, 1, $now));
        $this->assertCount(2, $logs);
        $this->assertSame('Обед', $logs[0]['food_description']);
        $this->assertCount(2, $users);
    }

    private function createDatabase(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tg_id INTEGER NULL)');
        $db->exec('CREATE TABLE user_events (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, event_name TEXT NOT NULL, event_data TEXT DEFAULT NULL, ip_address TEXT DEFAULT NULL, user_agent TEXT DEFAULT NULL, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE meals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL DEFAULT 1, food_description TEXT DEFAULT NULL, calories INTEGER NOT NULL DEFAULT 0, proteins REAL DEFAULT 0, fats REAL DEFAULT 0, carbs REAL DEFAULT 0, total_weight INTEGER DEFAULT NULL, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE ai_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, request_type TEXT NOT NULL DEFAULT "meal_analysis", status TEXT NOT NULL DEFAULT "success", response_time_ms INTEGER NULL, error_message TEXT DEFAULT NULL, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE system_logs (level TEXT NOT NULL, created_at TEXT NOT NULL)');

        return $db;
    }
}

class FakeDashboardStatsDatabaseConnection extends DatabaseConnection
{
    public function __construct(private readonly PDO $db) {}

    public function get(): PDO
    {
        return $this->db;
    }
}
