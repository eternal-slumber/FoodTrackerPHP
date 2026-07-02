<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use App\Core\DatabaseConnection;
use Throwable;

class AdminLoginAttemptRepository
{
    public function __construct(
        private readonly DatabaseConnection $database
    ) {}

    /** @return array{remaining:int, resets_in_seconds:int} */
    public function status(string $scope, string $action, int $limit, int $windowSeconds): array
    {
        $now = time();
        $windowStart = $this->windowStart($now, $windowSeconds);
        $statement = $this->database->get()->prepare(
            'SELECT attempts FROM rate_limits WHERE scope_key = ? AND action = ? AND window_start = ?'
        );
        $statement->execute([$scope, $action, date('Y-m-d H:i:s', $windowStart)]);
        $used = min($limit, max(0, (int)($statement->fetchColumn() ?: 0)));

        return [
            'remaining' => max(0, $limit - $used),
            'resets_in_seconds' => max(0, $windowStart + $windowSeconds - $now),
        ];
    }

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        $windowStart = date('Y-m-d H:i:s', $this->windowStart(time(), $windowSeconds));
        $database = $this->database->get();
        $database->beginTransaction();

        try {
            $statement = $database->prepare(
                'SELECT attempts FROM rate_limits
                 WHERE scope_key = ? AND action = ? AND window_start = ?
                 FOR UPDATE'
            );
            $statement->execute([$scope, $action, $windowStart]);
            $attempts = $statement->fetchColumn();

            if ($attempts === false) {
                $insert = $database->prepare(
                    'INSERT INTO rate_limits (scope_key, action, window_start, attempts) VALUES (?, ?, ?, 1)'
                );
                $insert->execute([$scope, $action, $windowStart]);
                $database->commit();

                return true;
            }

            if ((int)$attempts >= $limit) {
                $database->commit();

                return false;
            }

            $update = $database->prepare(
                'UPDATE rate_limits SET attempts = attempts + 1
                 WHERE scope_key = ? AND action = ? AND window_start = ?'
            );
            $update->execute([$scope, $action, $windowStart]);
            $database->commit();

            return true;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }
    }

    private function windowStart(int $timestamp, int $windowSeconds): int
    {
        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit window must be positive');
        }

        return $timestamp - ($timestamp % $windowSeconds);
    }
}
