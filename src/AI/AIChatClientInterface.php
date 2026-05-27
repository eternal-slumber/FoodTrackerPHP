<?php

declare(strict_types=1);

namespace App\AI;

interface AIChatClientInterface
{
    public function complete(array $messages, int $timeoutSeconds, string $operation): ?string;
}
