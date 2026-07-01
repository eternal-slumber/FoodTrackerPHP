<?php

declare(strict_types=1);

namespace App\Admin\Config;

class AdminConfig
{
    public function __construct(
        public readonly string $path,
        public readonly int $sessionLifetimeSeconds = 1800,
        public readonly int $loginAttemptLimit = 5,
        public readonly int $loginAttemptWindowSeconds = 900
    ) {}

    public static function fromEnv(array $env): self
    {
        $path = trim((string)($env['ADMIN_PATH'] ?? ''));

        if ($path === '') {
            return new self('');
        }

        $path = '/' . trim($path, "/ \t\n\r\0\x0B");
        $path = rtrim($path, '/');

        if ($path === '' || $path === '/admin' || $path === '/administrator') {
            throw new \InvalidArgumentException('ADMIN_PATH must be non-obvious and must not be /admin');
        }

        if (!preg_match('#^/[a-zA-Z0-9][a-zA-Z0-9/_-]{7,127}$#', $path)) {
            throw new \InvalidArgumentException('ADMIN_PATH must be 8-128 chars and contain only letters, numbers, slash, underscore or dash');
        }

        return new self(
            $path,
            self::boundedInteger($env, 'ADMIN_SESSION_LIFETIME_SECONDS', 1800, 300, 86400),
            self::boundedInteger($env, 'ADMIN_LOGIN_ATTEMPT_LIMIT', 5, 1, 20),
            self::boundedInteger($env, 'ADMIN_LOGIN_ATTEMPT_WINDOW_SECONDS', 900, 60, 86400)
        );
    }

    public function isEnabled(): bool
    {
        return $this->path !== '';
    }

    private static function boundedInteger(
        array $env,
        string $key,
        int $default,
        int $minimum,
        int $maximum
    ): int {
        $value = filter_var($env[$key] ?? $default, FILTER_VALIDATE_INT);

        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException($key . ' is outside the allowed range');
        }

        return $value;
    }
}
