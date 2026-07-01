<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use App\Core\DatabaseConnection;
use PDO;

class SystemLogRepository
{
    public function __construct(
        private readonly DatabaseConnection $database
    ) {}

    /**
     * @return array{
     *     items:list<array{id:int, level:string, channel:string, message:string, exception_class:?string, trace_id:?string, created_at:string}>,
     *     total:int,
     *     page:int,
     *     per_page:int,
     *     total_pages:int
     * }
     */
    public function search(
        string $level,
        string $channel,
        ?string $startUtc,
        ?string $endUtc,
        int $page,
        int $perPage = 50
    ): array {
        $perPage = max(10, min(100, $perPage));
        [$whereSql, $params] = $this->buildFilters($level, $channel, $startUtc, $endUtc);

        $countStatement = $this->db()->prepare('SELECT COUNT(*) FROM system_logs' . $whereSql);
        $countStatement->execute($params);
        $total = (int)$countStatement->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $statement = $this->db()->prepare(
            'SELECT id, level, channel, message, exception_class, trace_id, created_at
             FROM system_logs' . $whereSql . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => array_values(array_filter($rows, 'is_array')),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return list<string> */
    public function channels(): array
    {
        $statement = $this->db()->query(
            "SELECT DISTINCT channel
             FROM system_logs
             WHERE channel <> ''
             ORDER BY channel ASC"
        );

        return array_values(array_filter(
            array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)),
            static fn(string $channel): bool => $channel !== ''
        ));
    }

    /** @return array{0:string, 1:array<string, string>} */
    private function buildFilters(
        string $level,
        string $channel,
        ?string $startUtc,
        ?string $endUtc
    ): array {
        $conditions = [];
        $params = [];

        if ($level === 'errors') {
            $conditions[] = "level IN ('error', 'critical')";
        } elseif ($level !== '') {
            $conditions[] = 'level = :level';
            $params['level'] = $level;
        }

        if ($channel !== '') {
            $conditions[] = 'channel = :channel';
            $params['channel'] = $channel;
        }

        if ($startUtc !== null && $endUtc !== null) {
            $conditions[] = 'created_at >= :start_at';
            $conditions[] = 'created_at < :end_at';
            $params['start_at'] = $startUtc;
            $params['end_at'] = $endUtc;
        }

        if ($conditions === []) {
            return ['', $params];
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function db(): PDO
    {
        return $this->database->get();
    }
}
