<?php

declare(strict_types=1);

namespace App\Admin\Services;

class AppHealthService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeoutSeconds = 1.0
    ) {}

    public function isAvailable(): bool
    {
        if ($this->host === '' || $this->port < 1 || $this->port > 65535) {
            return false;
        }

        $connection = @fsockopen(
            $this->host,
            $this->port,
            $errorCode,
            $errorMessage,
            $this->timeoutSeconds
        );

        if (!is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
