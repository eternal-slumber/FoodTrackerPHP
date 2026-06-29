<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Meal;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

class MealRepository
{
    public function __construct(private readonly PDO $db) {}

    public function save(Meal $meal): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO meals (user_id, food_description, calories, proteins, fats, carbs, total_weight, image_path)
             VALUES (:user_id, :description, :calories, :proteins, :fats, :carbs, :total_weight, :image_path)'
        );

        $result = $stmt->execute([
            'user_id' => $meal->userId,
            'description' => $meal->description,
            'calories' => $meal->calories,
            'proteins' => $meal->proteins,
            'fats' => $meal->fats,
            'carbs' => $meal->carbs,
            'total_weight' => $meal->totalWeight,
            'image_path' => $meal->imagePath,
        ]);

        if ($result) {
            $meal->id = (int)$this->db->lastInsertId();
        }

        return $result;
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM meals WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);

        return array_map(
            fn(array $data): Meal => $this->hydrate($data),
            $stmt->fetchAll()
        );
    }

    /** @return list<array<string, int|float|string>> */
    public function findForLocalDay(
        int $userId,
        int $timezoneOffsetMinutes = 0,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        [$startUtc, $endUtc] = $this->localDayUtcRange($timezoneOffsetMinutes, $nowUtc);
        $stmt = $this->db->prepare(
            'SELECT id, food_description, calories, proteins, fats, carbs, created_at
             FROM meals
             WHERE user_id = :user_id AND created_at >= :start_utc AND created_at < :end_utc
             ORDER BY created_at ASC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'start_utc' => $startUtc->format('Y-m-d H:i:s'),
            'end_utc' => $endUtc->format('Y-m-d H:i:s'),
        ]);

        $localModifier = sprintf('%+d minutes', -$timezoneOffsetMinutes);

        return array_map(function (array $row) use ($localModifier): array {
            $createdAt = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string)$row['created_at'],
                new DateTimeZone('UTC')
            );

            return [
                'id' => (int)$row['id'],
                'name' => substr(trim((string)$row['food_description']), 0, 120),
                'time' => $createdAt instanceof DateTimeImmutable
                    ? $createdAt->modify($localModifier)->format('H:i')
                    : '',
                'calories' => (int)$row['calories'],
                'proteins' => round((float)$row['proteins'], 1),
                'fats' => round((float)$row['fats'], 1),
                'carbs' => round((float)$row['carbs'], 1),
            ];
        }, $stmt->fetchAll());
    }

    /** @return list<string> Local dates in YYYY-MM-DD format, newest first. */
    public function findActiveMealDates(int $userId, int $timezoneOffsetMinutes = 0): array
    {
        if ($timezoneOffsetMinutes < -840 || $timezoneOffsetMinutes > 840) {
            throw new \InvalidArgumentException('Invalid timezone offset');
        }

        // JavaScript getTimezoneOffset() is UTC - local time, hence DATE_SUB.
        $localDateExpression = sprintf(
            'DATE(DATE_SUB(created_at, INTERVAL %d MINUTE))',
            $timezoneOffsetMinutes
        );
        $stmt = $this->db->prepare(
            "SELECT DISTINCT {$localDateExpression} AS active_date
             FROM meals
             WHERE user_id = :user_id
             ORDER BY active_date DESC"
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map(
            static fn(array $row): string => (string)$row['active_date'],
            $stmt->fetchAll()
        );
    }

    public function getDailyCaloriesForMonth(int $userId, string $month, int $timezoneOffsetMinutes): array
    {
        $startLocal = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $month . '-01 00:00:00',
            new DateTimeZone('UTC')
        );

        if (!$startLocal instanceof DateTimeImmutable) {
            return [];
        }

        $offsetModifier = sprintf('%+d minutes', $timezoneOffsetMinutes);
        $localModifier = sprintf('%+d minutes', -$timezoneOffsetMinutes);
        $startUtc = $startLocal->modify($offsetModifier);
        $endUtc = $startLocal->modify('first day of next month')->modify($offsetModifier);

        $stmt = $this->db->prepare(
            'SELECT id, food_description, calories, proteins, fats, carbs, total_weight, image_path, created_at
             FROM meals
             WHERE user_id = :user_id
               AND created_at >= :start_utc
               AND created_at < :end_utc
             ORDER BY created_at ASC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'start_utc' => $startUtc->format('Y-m-d H:i:s'),
            'end_utc' => $endUtc->format('Y-m-d H:i:s'),
        ]);

        $dailyCalories = [];
        foreach ($stmt->fetchAll() as $row) {
            $createdAt = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                (string)$row['created_at'],
                new DateTimeZone('UTC')
            );

            if (!$createdAt instanceof DateTimeImmutable) {
                continue;
            }

            $localCreatedAt = $createdAt->modify($localModifier);
            $localDate = $localCreatedAt->format('Y-m-d');

            if (!isset($dailyCalories[$localDate])) {
                $dailyCalories[$localDate] = [
                    'date' => $localDate,
                    'calories' => 0,
                    'proteins' => 0.0,
                    'fats' => 0.0,
                    'carbs' => 0.0,
                    'weight' => 0,
                    'meals' => [],
                ];
            }

            $calories = (int)$row['calories'];
            $proteins = (float)$row['proteins'];
            $fats = (float)$row['fats'];
            $carbs = (float)$row['carbs'];
            $weight = isset($row['total_weight']) ? (int)$row['total_weight'] : 0;

            $dailyCalories[$localDate]['calories'] += $calories;
            $dailyCalories[$localDate]['proteins'] += $proteins;
            $dailyCalories[$localDate]['fats'] += $fats;
            $dailyCalories[$localDate]['carbs'] += $carbs;
            $dailyCalories[$localDate]['weight'] += $weight;
            $dailyCalories[$localDate]['meals'][] = [
                'id' => (int)$row['id'],
                'description' => (string)$row['food_description'],
                'calories' => $calories,
                'proteins' => $proteins,
                'fats' => $fats,
                'carbs' => $carbs,
                'weight' => $weight,
                'time' => $localCreatedAt->format('H:i'),
                'thumbnail_url' => !empty($row['image_path'])
                    ? '/api/meals/' . (int)$row['id'] . '/thumbnail'
                    : null,
            ];
        }

        return array_values($dailyCalories);
    }

    public function findById(int $id): ?Meal
    {
        $stmt = $this->db->prepare('SELECT * FROM meals WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        return $data ? $this->hydrate($data) : null;
    }

    public function findAllImagePaths(): array
    {
        $stmt = $this->db->query(
            "SELECT image_path FROM meals WHERE image_path IS NOT NULL AND image_path <> ''"
        );

        return array_map(
            static fn(array $row): string => (string)$row['image_path'],
            $stmt->fetchAll()
        );
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM meals WHERE id = ?');

        return $stmt->execute([$id]);
    }

    private function hydrate(array $data): Meal
    {
        return new Meal(
            userId: (int)$data['user_id'],
            description: $data['food_description'],
            calories: (int)$data['calories'],
            proteins: (float)$data['proteins'],
            fats: (float)$data['fats'],
            carbs: (float)$data['carbs'],
            totalWeight: isset($data['total_weight']) ? (int)$data['total_weight'] : null,
            imagePath: $data['image_path'],
            id: (int)$data['id'],
            createdAt: $this->formatUtcTimestamp($data['created_at'] ?? null)
        );
    }

    private function formatUtcTimestamp(?string $timestamp): ?string
    {
        if (!$timestamp) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $timestamp,
            new DateTimeZone('UTC')
        );

        return $date instanceof DateTimeImmutable
            ? $date->format('Y-m-d\TH:i:s\Z')
            : $timestamp;
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable} */
    private function localDayUtcRange(
        int $timezoneOffsetMinutes,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $localDate = $nowUtc
            ->modify(sprintf('%+d minutes', -$timezoneOffsetMinutes))
            ->format('Y-m-d');
        $startLocal = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $localDate . ' 00:00:00',
            new DateTimeZone('UTC')
        );

        if (!$startLocal instanceof DateTimeImmutable) {
            throw new \RuntimeException('Failed to build local day range');
        }

        $utcModifier = sprintf('%+d minutes', $timezoneOffsetMinutes);

        return [
            $startLocal->modify($utcModifier),
            $startLocal->modify('+1 day')->modify($utcModifier),
        ];
    }
}
