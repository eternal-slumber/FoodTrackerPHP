<?php

declare(strict_types=1);

namespace App\Admin\Config;

class AdminConfig
{
    public function __construct(public readonly string $path) {}

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

        return new self($path);
    }

    public function isEnabled(): bool
    {
        return $this->path !== '';
    }
}

