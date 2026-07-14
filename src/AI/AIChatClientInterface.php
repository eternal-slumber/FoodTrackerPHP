<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\Exceptions\AIAuthenticationException;
use App\AI\Exceptions\AIConfigurationException;
use App\AI\Exceptions\AIInvalidResponseException;
use App\AI\Exceptions\AIRateLimitException;
use App\AI\Exceptions\AIRetryableException;

interface AIChatClientInterface
{
    /**
     * @param list<array{
     *     role: string,
     *     content: string|list<array<string, mixed>>
     * }> $messages
     * @param array{
     *     model_purpose?: string,
     *     max_tokens?: int,
     *     temperature?: float,
     *     json_schema?: array<string, mixed>
     * } $options
     *
     * @throws AIAuthenticationException
     * @throws AIConfigurationException
     * @throws AIInvalidResponseException
     * @throws AIRateLimitException
     * @throws AIRetryableException
     */
    public function complete(
        array $messages,
        int $timeoutSeconds,
        string $operation,
        array $options = []
    ): string;
}
