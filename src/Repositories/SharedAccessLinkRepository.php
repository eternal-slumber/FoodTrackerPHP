<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SharedAccessLinkRepository
{
    public function __construct(private readonly PDO $db) {}

    public function findActiveForUser(int $userId, string $nowUtc): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM shared_access_links
             WHERE user_id = :user_id AND type = 'trainer' AND is_active = 1
               AND (expires_at IS NULL OR expires_at > :now_utc)
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'now_utc' => $nowUtc]);
        $link = $stmt->fetch();

        return is_array($link) ? $link : null;
    }

    public function findActiveByToken(string $token, string $nowUtc): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT links.*, users.daily_goal, users.weight, users.height, users.age,
                    users.gender, users.goal
             FROM shared_access_links AS links
             INNER JOIN users ON users.id = links.user_id
             WHERE links.token = :token AND links.type = 'trainer'
               AND links.is_active = 1 AND (links.expires_at IS NULL OR links.expires_at > :now_utc)
             LIMIT 1"
        );
        $stmt->execute(['token' => $token, 'now_utc' => $nowUtc]);
        $link = $stmt->fetch();

        return is_array($link) ? $link : null;
    }

    public function replaceForUser(
        int $userId,
        string $token,
        string $displayName,
        int $timezoneOffset,
        ?string $expiresAtUtc,
        string $nowUtc,
        ?int $durationDays,
        array $visibility
    ): array {
        $this->db->beginTransaction();
        try {
            $revoke = $this->db->prepare(
                "UPDATE shared_access_links SET is_active = 0, revoked_at = :now_utc
                 WHERE user_id = :user_id AND type = 'trainer' AND is_active = 1"
            );
            $revoke->execute(['user_id' => $userId, 'now_utc' => $nowUtc]);

            $insert = $this->db->prepare(
                "INSERT INTO shared_access_links
                    (user_id, token, type, display_name, timezone_offset, duration_days,
                     show_meals, show_nutrition, show_history, show_ai_analysis,
                     show_profile_params, expires_at)
                 VALUES (:user_id, :token, 'trainer', :display_name, :timezone_offset, :duration_days,
                     :show_meals, :show_nutrition, :show_history, :show_ai_analysis,
                     :show_profile_params, :expires_at)"
            );
            $insert->execute([
                'user_id' => $userId,
                'token' => $token,
                'display_name' => $displayName,
                'timezone_offset' => $timezoneOffset,
                'duration_days' => $durationDays,
                'show_meals' => $visibility['meals'] ? 1 : 0,
                'show_nutrition' => $visibility['nutrition'] ? 1 : 0,
                'show_history' => $visibility['history'] ? 1 : 0,
                'show_ai_analysis' => $visibility['ai_analysis'] ? 1 : 0,
                'show_profile_params' => $visibility['profile_params'] ? 1 : 0,
                'expires_at' => $expiresAtUtc,
            ]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return [
            'token' => $token,
            'display_name' => $displayName,
            'timezone_offset' => $timezoneOffset,
            'duration_days' => $durationDays,
            'visibility' => $visibility,
            'expires_at' => $expiresAtUtc,
        ];
    }

    public function updateActiveForUser(
        int $userId,
        ?string $expiresAtUtc,
        ?int $durationDays,
        array $visibility
    ): void {
        $stmt = $this->db->prepare(
            "UPDATE shared_access_links
             SET expires_at = :expires_at, duration_days = :duration_days,
                 show_meals = :show_meals, show_nutrition = :show_nutrition,
                 show_history = :show_history, show_ai_analysis = :show_ai_analysis,
                 show_profile_params = :show_profile_params
             WHERE user_id = :user_id AND type = 'trainer' AND is_active = 1"
        );
        $stmt->execute([
            'user_id' => $userId,
            'expires_at' => $expiresAtUtc,
            'duration_days' => $durationDays,
            'show_meals' => $visibility['meals'] ? 1 : 0,
            'show_nutrition' => $visibility['nutrition'] ? 1 : 0,
            'show_history' => $visibility['history'] ? 1 : 0,
            'show_ai_analysis' => $visibility['ai_analysis'] ? 1 : 0,
            'show_profile_params' => $visibility['profile_params'] ? 1 : 0,
        ]);
    }

    public function revokeForUser(int $userId, string $nowUtc): void
    {
        $stmt = $this->db->prepare(
            "UPDATE shared_access_links SET is_active = 0, revoked_at = :now_utc
             WHERE user_id = :user_id AND type = 'trainer' AND is_active = 1"
        );
        $stmt->execute(['user_id' => $userId, 'now_utc' => $nowUtc]);
    }

    public function touchViewed(int $id, string $viewedAtUtc): void
    {
        $stmt = $this->db->prepare(
            'UPDATE shared_access_links SET last_viewed_at = :viewed_at WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'viewed_at' => $viewedAtUtc]);
    }
}
