<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class RateLimiterService
{
    public function __construct(private readonly PDO $db) {}

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        $windowStart = time() - (time() % $windowSeconds);
        $windowStartSql = date('Y-m-d H:i:s', $windowStart);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT attempts FROM rate_limits WHERE scope_key = ? AND action = ? AND window_start = ? FOR UPDATE'
            );
            $stmt->execute([$scope, $action, $windowStartSql]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $this->db->prepare(
                    'INSERT INTO rate_limits (scope_key, action, window_start, attempts) VALUES (?, ?, ?, 1)'
                );
                $insert->execute([$scope, $action, $windowStartSql]);
                $this->db->commit();
                return true;
            }

            if ((int)$row['attempts'] >= $limit) {
                $this->db->commit();
                return false;
            }

            $update = $this->db->prepare(
                'UPDATE rate_limits SET attempts = attempts + 1 WHERE scope_key = ? AND action = ? AND window_start = ?'
            );
            $update->execute([$scope, $action, $windowStartSql]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
