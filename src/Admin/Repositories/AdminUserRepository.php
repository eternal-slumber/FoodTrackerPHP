<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use App\Core\DatabaseConnection;
use PDO;

class AdminUserRepository
{
    public function __construct(
        private readonly DatabaseConnection $database
    ) {}

    /**
     * @return array{id:int, admin_login:?string, password_hash:?string, username:?string, role:string}|null
     */
    public function findActiveByLogin(string $login): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, admin_login, password_hash, username, role
             FROM admin_users
             WHERE admin_login = :admin_login AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['admin_login' => $login]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{id:int, admin_login:?string, username:?string, role:string}|null
     */
    public function findActiveById(int $adminUserId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, admin_login, username, role
             FROM admin_users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $adminUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markLogin(int $adminUserId): void
    {
        $stmt = $this->db()->prepare('UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $stmt->execute(['id' => $adminUserId]);
    }

    public function writeLoginAuditLog(int $adminUserId, string $ipAddress, string $userAgent): void
    {
        $stmt = $this->db()->prepare(
            'INSERT INTO admin_audit_logs (admin_user_id, action, entity_type, ip_address, user_agent)
             VALUES (:admin_user_id, :action, :entity_type, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'admin_user_id' => $adminUserId,
            'action' => 'login',
            'entity_type' => 'admin_session',
            'ip_address' => substr($ipAddress, 0, 45),
            'user_agent' => substr($userAgent, 0, 500),
        ]);
    }

    private function db(): PDO
    {
        return $this->database->get();
    }
}
