<?php

declare(strict_types=1);

namespace App\Admin\Auth;

use App\Admin\Config\AdminConfig;
use App\Admin\Repositories\AdminLoginAttemptRepository;

class AdminLoginGuard
{
    private const ACTION = 'admin_login_failed';

    public function __construct(
        private readonly AdminConfig $adminConfig,
        private readonly AdminLoginAttemptRepository $loginAttempts
    ) {}

    /** @return array{blocked:bool, retry_after:int} */
    public function status(string $login, string $ipAddress): array
    {
        $status = $this->loginAttempts->status(
            $this->scope($login, $ipAddress),
            self::ACTION,
            $this->adminConfig->loginAttemptLimit,
            $this->adminConfig->loginAttemptWindowSeconds
        );

        return [
            'blocked' => $status['remaining'] === 0,
            'retry_after' => $status['resets_in_seconds'],
        ];
    }

    public function registerFailure(string $login, string $ipAddress): void
    {
        $this->loginAttempts->consume(
            $this->scope($login, $ipAddress),
            self::ACTION,
            $this->adminConfig->loginAttemptLimit,
            $this->adminConfig->loginAttemptWindowSeconds
        );
    }

    private function scope(string $login, string $ipAddress): string
    {
        return hash('sha256', strtolower(trim($login)) . '|' . trim($ipAddress));
    }
}
