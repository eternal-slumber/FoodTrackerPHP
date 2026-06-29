<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class DailyNutritionInsightRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findForUserAndDate(int $userId, string $localDate): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, local_date, timezone_offset, context_hash, short_summary,
                    day_analysis, next_meal, model, generated_at
             FROM daily_nutrition_insights
             WHERE user_id = :user_id AND local_date = :local_date
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'local_date' => $localDate,
        ]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $nextMeal = json_decode((string)$row['next_meal'], true);
        $row['next_meal'] = is_array($nextMeal) ? $nextMeal : [];

        return $row;
    }

    public function save(
        int $userId,
        string $localDate,
        int $timezoneOffsetMinutes,
        string $contextHash,
        array $insight,
        string $model,
        string $generatedAt
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO daily_nutrition_insights (
                user_id, local_date, timezone_offset, context_hash, short_summary,
                day_analysis, next_meal, model, generated_at
             ) VALUES (
                :user_id, :local_date, :timezone_offset, :context_hash, :short_summary,
                :day_analysis, :next_meal, :model, :generated_at
             )
             ON DUPLICATE KEY UPDATE
                timezone_offset = VALUES(timezone_offset),
                context_hash = VALUES(context_hash),
                short_summary = VALUES(short_summary),
                day_analysis = VALUES(day_analysis),
                next_meal = VALUES(next_meal),
                model = VALUES(model),
                generated_at = VALUES(generated_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'local_date' => $localDate,
            'timezone_offset' => $timezoneOffsetMinutes,
            'context_hash' => $contextHash,
            'short_summary' => (string)$insight['short_summary'],
            'day_analysis' => (string)$insight['day_analysis'],
            'next_meal' => json_encode($insight['next_meal'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'model' => $model,
            'generated_at' => $generatedAt,
        ]);
    }

    public function deleteForUserAndDate(int $userId, string $localDate): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM daily_nutrition_insights WHERE user_id = :user_id AND local_date = :local_date'
        );
        $stmt->execute([
            'user_id' => $userId,
            'local_date' => $localDate,
        ]);
    }
}
