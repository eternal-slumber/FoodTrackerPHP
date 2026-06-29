<?php

declare(strict_types=1);

namespace App\Admin\Auth;

class AdminSession
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }

    public function currentAdminId(): ?int
    {
        $this->start();

        $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;

        return $adminId > 0 ? $adminId : null;
    }

    public function signIn(int $adminUserId): void
    {
        $this->start();
        session_regenerate_id(true);

        $_SESSION['admin_user_id'] = $adminUserId;
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
