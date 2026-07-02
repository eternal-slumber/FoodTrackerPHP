<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ReminderScheduleRepository
{
    public function __construct(private readonly PDO $db) {}

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

    public function upsertSetting(
        int $userId,
        string $mealType,
        string $reminderTime,
        int $remindBeforeMinutes,
        int $timezoneOffsetMinutes,
        string $lastMealAtUtc
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO user_meal_reminder_settings (
                user_id, meal_type, reminder_time, remind_before_minutes,
                timezone_offset, last_meal_at
             ) VALUES (
                :user_id, :meal_type, :reminder_time, :remind_before_minutes,
                :timezone_offset, :last_meal_at
             )
             ON DUPLICATE KEY UPDATE
                reminder_time = VALUES(reminder_time),
                remind_before_minutes = VALUES(remind_before_minutes),
                timezone_offset = VALUES(timezone_offset),
                last_meal_at = VALUES(last_meal_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'meal_type' => $mealType,
            'reminder_time' => $reminderTime,
            'remind_before_minutes' => $remindBeforeMinutes,
            'timezone_offset' => $timezoneOffsetMinutes,
            'last_meal_at' => $lastMealAtUtc,
        ]);
    }

    public function upsertNotification(
        int $userId,
        string $notificationType,
        string $mealType,
        string $localDate,
        string $sendAtUtc,
        array $payload = []
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO notification_queue (
                user_id, notification_type, meal_type, local_date, send_at, payload
             ) VALUES (
                :user_id, :notification_type, :meal_type, :local_date, :send_at, :payload
             )
             ON DUPLICATE KEY UPDATE
                send_at = VALUES(send_at),
                status = \'pending\',
                attempts = 0,
                sent_at = NULL,
                last_error = NULL,
                payload = VALUES(payload)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'notification_type' => $notificationType,
            'meal_type' => $mealType,
            'local_date' => $localDate,
            'send_at' => $sendAtUtc,
            'payload' => $payload === []
                ? null
                : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function claimDueNotifications(
        string $nowUtc,
        string $staleProcessingBeforeUtc,
        int $limit = 50
    ): array {
        $limit = max(1, min(100, $limit));
        $this->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT
                    queue.id,
                    queue.user_id,
                    queue.notification_type,
                    queue.meal_type,
                    queue.local_date,
                    queue.send_at,
                    queue.attempts,
                    users.tg_id AS telegram_id,
                    settings.reminder_time,
                    settings.remind_before_minutes,
                    settings.timezone_offset,
                    settings.enabled AS setting_enabled
                 FROM notification_queue AS queue
                 INNER JOIN users ON users.id = queue.user_id
                 LEFT JOIN user_meal_reminder_settings AS settings
                    ON settings.user_id = queue.user_id
                   AND settings.meal_type = queue.meal_type
                 WHERE (
                    queue.status = \'pending\' AND queue.send_at <= :now_utc
                 ) OR (
                    queue.status = \'processing\' AND queue.updated_at <= :stale_before_utc
                 )
                 ORDER BY queue.send_at ASC, queue.id ASC
                 LIMIT ' . $limit . '
                 FOR UPDATE SKIP LOCKED'
            );
            $stmt->execute([
                'now_utc' => $nowUtc,
                'stale_before_utc' => $staleProcessingBeforeUtc,
            ]);
            $notifications = $stmt->fetchAll();

            if ($notifications !== []) {
                $ids = array_map(static fn(array $row): int => (int)$row['id'], $notifications);
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $claim = $this->db->prepare(
                    "UPDATE notification_queue
                     SET status = 'processing', attempts = attempts + 1, last_error = NULL
                     WHERE id IN ({$placeholders})"
                );
                $claim->execute($ids);
            }

            $this->commit();

            return $notifications;
        } catch (\Throwable $error) {
            $this->rollBack();
            throw $error;
        }
    }

    public function markSent(int $notificationId, string $sentAtUtc): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notification_queue
             SET status = 'sent', sent_at = :sent_at, last_error = NULL
             WHERE id = :id AND status = 'processing'"
        );
        $stmt->execute(['id' => $notificationId, 'sent_at' => $sentAtUtc]);
    }

    public function markSkipped(int $notificationId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notification_queue
             SET status = 'skipped', last_error = NULL
             WHERE id = :id AND status = 'processing'"
        );
        $stmt->execute(['id' => $notificationId]);
    }

    public function markFailed(int $notificationId, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notification_queue
             SET status = 'failed', last_error = :last_error
             WHERE id = :id AND status = 'processing'"
        );
        $stmt->execute([
            'id' => $notificationId,
            'last_error' => substr($errorMessage, 0, 2000),
        ]);
    }
}
