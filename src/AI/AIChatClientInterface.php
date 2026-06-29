<?php

declare(strict_types=1);

namespace App\AI;

interface AIChatClientInterface
{
    /**
     * @param array{model_purpose?: string, max_tokens?: int, temperature?: float, json_schema?: array} $options
     */
    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): ?string;
}
