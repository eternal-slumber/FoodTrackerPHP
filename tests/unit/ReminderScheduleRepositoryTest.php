<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ReminderScheduleRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class ReminderScheduleRepositoryTest extends TestCase
{
    public function testEveningSummaryUpsertOnlyChangesPendingNotification(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement
            ->expects($this->once())
            ->method('execute')
            ->with([
                'user_id' => 7,
                'local_date' => '2026-07-10',
                'send_at' => '2026-07-10 16:25:00',
                'payload' => '{"timezone_offset":-180}',
            ]);

        $db = $this->createMock(PDO::class);
        $db
            ->expects($this->once())
            ->method('prepare')
            ->with($this->callback(static function (string $sql): bool {
                return str_contains(
                    $sql,
                    "send_at = IF(status = 'pending', VALUES(send_at), send_at)"
                ) && str_contains(
                    $sql,
                    "payload = IF(status = 'pending', VALUES(payload), payload)"
                ) && preg_match('/^\s*status\s*=/m', $sql) !== 1;
            }))
            ->willReturn($statement);

        $repository = new ReminderScheduleRepository($db);

        $repository->scheduleEveningSummary(
            7,
            '2026-07-10',
            '2026-07-10 16:25:00',
            -180
        );
    }
}
