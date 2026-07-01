<?php

declare(strict_types=1);

namespace App\Admin\Auth;

use App\Admin\Config\AdminConfig;

class AdminSession
{
    private const SESSION_NAME = 'foodtracker_admin';

    public function __construct(
        private readonly AdminConfig $adminConfig
    ) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(self::SESSION_NAME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)$this->adminConfig->sessionLifetimeSeconds);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->adminConfig->path,
            'secure' => $this->isHttpsRequest(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }

    public function currentAdminId(): ?int
    {
        $this->start();

        if ($this->hasExpired()) {
            $this->destroy();

            return null;
        }

        $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : 0;

        if ($adminId > 0) {
            $_SESSION['admin_last_activity_at'] = time();
        }

        return $adminId > 0 ? $adminId : null;
    }

    public function signIn(int $adminUserId): void
    {
        $this->start();
        session_regenerate_id(true);

        $_SESSION['admin_user_id'] = $adminUserId;
        $_SESSION['admin_last_activity_at'] = time();
        $this->csrfToken();
    }

    public function csrfToken(): string
    {
        $this->start();

        $token = $_SESSION['admin_csrf_token'] ?? null;
        if (!is_string($token) || strlen($token) < 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['admin_csrf_token'] = $token;
        }

        return $token;
    }

    public function isValidCsrfToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return hash_equals($this->csrfToken(), $token);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $cookie['path'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Strict',
            ]);
            session_destroy();
        }
    }

    private function hasExpired(): bool
    {
        $lastActivityAt = isset($_SESSION['admin_last_activity_at'])
            ? (int)$_SESSION['admin_last_activity_at']
            : 0;

        return $lastActivityAt > 0
            && time() - $lastActivityAt > $this->adminConfig->sessionLifetimeSeconds;
    }

    private function isHttpsRequest(): bool
    {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return ($https !== '' && $https !== 'off') || $forwardedProto === 'https';
    }
}
