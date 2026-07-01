<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Repositories\SystemLogRepository;
use App\Core\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class SystemLogRepositoryTest extends TestCase
{
    public function testFiltersErrorsByChannelAndUtcRange(): void
    {
        $db = $this->database();
        $repository = new SystemLogRepository(new FakeSystemLogDatabaseConnection($db));

        $db->exec(
            "INSERT INTO system_logs (id, level, channel, message, created_at) VALUES
             (1, 'error', 'http', 'First error', '2026-06-29 21:00:00'),
             (2, 'critical', 'http', 'Critical error', '2026-06-30 10:00:00'),
             (3, 'warning', 'http', 'Warning', '2026-06-30 11:00:00'),
             (4, 'error', 'ai', 'AI error', '2026-06-30 12:00:00'),
             (5, 'error', 'http', 'Next day', '2026-06-30 21:00:00')"
        );

        $page = $repository->search(
            'errors',
            'http',
            '2026-06-29 21:00:00',
            '2026-06-30 21:00:00',
            1
        );

        $this->assertSame(2, $page['total']);
        $this->assertSame([2, 1], array_column($page['items'], 'id'));
        $this->assertSame(['ai', 'http'], $repository->channels());
    }

    private function database(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec(
            'CREATE TABLE system_logs (
                id INTEGER PRIMARY KEY,
                level TEXT NOT NULL,
                channel TEXT NOT NULL,
                message TEXT NOT NULL,
                exception_class TEXT NULL,
                trace_id TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );

        return $db;
    }
}

class FakeSystemLogDatabaseConnection extends DatabaseConnection
{
    public function __construct(private readonly PDO $db) {}

    public function get(): PDO
    {
        return $this->db;
    }
}
