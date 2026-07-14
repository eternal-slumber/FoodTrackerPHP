<?php

declare(strict_types=1);

namespace App\AI;

final class CurlTestState
{
    public static string|false $response = '';
    public static int $httpCode = 200;
    public static int $errorCode = 0;
}

function curl_init(string $url): object
{
    return (object)['url' => $url];
}

function curl_setopt(object $handle, int $option, mixed $value): bool
{
    return true;
}

function curl_exec(object $handle): string|false
{
    return CurlTestState::$response;
}

function curl_getinfo(object $handle, int $option): int
{
    return CurlTestState::$httpCode;
}

function curl_errno(object $handle): int
{
    return CurlTestState::$errorCode;
}

namespace Tests\Unit;

use App\AI\CurlTestState;
use App\AI\Exceptions\AIAuthenticationException;
use App\AI\Exceptions\AIConfigurationException;
use App\AI\Exceptions\AIInvalidResponseException;
use App\AI\Exceptions\AIRateLimitException;
use App\AI\Exceptions\AIRetryableException;
use App\AI\OpenAICompatibleChatClient;
use App\Config\AIProviderConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenAICompatibleChatClientTest extends TestCase
{
    protected function setUp(): void
    {
        CurlTestState::$response = '{"choices":[{"message":{"content":"ok"}}]}';
        CurlTestState::$httpCode = 200;
        CurlTestState::$errorCode = 0;
    }

    public function testReturnsContentForSuccessfulResponse(): void
    {
        $this->assertSame('ok', $this->client()->complete([], 10, 'test'));
    }

    #[DataProvider('httpErrors')]
    public function testClassifiesHttpErrors(int $httpCode, string $exceptionClass, int $applicationCode): void
    {
        CurlTestState::$httpCode = $httpCode;
        CurlTestState::$response = '{"error":"provider error"}';

        $this->expectException($exceptionClass);
        $this->expectExceptionCode($applicationCode);

        $this->client()->complete([], 10, 'test');
    }

    public static function httpErrors(): array
    {
        return [
            'unauthorized' => [401, AIAuthenticationException::class, 503],
            'forbidden' => [403, AIAuthenticationException::class, 503],
            'rate limit' => [429, AIRateLimitException::class, 429],
            'request timeout' => [408, AIRetryableException::class, 503],
            'server error' => [500, AIRetryableException::class, 503],
            'bad request' => [400, AIConfigurationException::class, 502],
        ];
    }

    public function testTreatsTransportFailureAsRetryable(): void
    {
        CurlTestState::$response = false;
        CurlTestState::$httpCode = 0;
        CurlTestState::$errorCode = 28;

        $this->expectException(AIRetryableException::class);

        $this->client()->complete([], 10, 'test');
    }

    public function testRejectsMalformedJsonResponse(): void
    {
        CurlTestState::$response = '{broken';

        $this->expectException(AIInvalidResponseException::class);

        $this->client()->complete([], 10, 'test');
    }

    public function testRejectsResponseWithoutContent(): void
    {
        CurlTestState::$response = '{"choices":[]}';

        $this->expectException(AIInvalidResponseException::class);

        $this->client()->complete([], 10, 'test');
    }

    public function testRejectsRequestThatCannotBeEncoded(): void
    {
        $this->expectException(AIConfigurationException::class);

        $this->client()->complete([['content' => INF]], 10, 'test');
    }

    private function client(): OpenAICompatibleChatClient
    {
        return new OpenAICompatibleChatClient(new AIProviderConfig(
            provider: 'test',
            baseUrl: 'https://ai.example.test/v1',
            apiKey: 'secret',
            model: 'test-model',
            textModel: 'test-model',
            visionModel: 'test-model'
        ));
    }
}
