<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AppException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\ErrorMiddlewareFactory;
use App\Services\TelemetryService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

class ErrorMiddlewareFactoryTest extends TestCase
{
    public function testHandlesValidationExceptionWithoutErrorTelemetry(): void
    {
        [$response, $telemetry] = $this->handle(new ValidationException('Invalid input', ['field' => 'required']));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Invalid input', $this->payload($response)['error']);
        $this->assertSame(0, $telemetry->recordedErrors);
    }

    public function testHandlesServerAppExceptionAndRecordsItOnce(): void
    {
        [$response, $telemetry] = $this->handle(new AppException('Service unavailable', 503));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('Service unavailable', $this->payload($response)['error']);
        $this->assertSame(1, $telemetry->recordedErrors);
    }

    public function testHandlesNotFoundExceptionWithoutErrorTelemetry(): void
    {
        [$response, $telemetry] = $this->handle(new NotFoundException());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Not Found', $this->payload($response)['error']);
        $this->assertSame(0, $telemetry->recordedErrors);
    }

    public function testHandlesUnexpectedErrorAsInternalServerError(): void
    {
        [$response, $telemetry] = $this->handle(new \TypeError('Sensitive implementation detail'));
        $payload = $this->payload($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal Server Error', $payload['error']);
        $this->assertArrayNotHasKey('message', $payload);
        $this->assertSame(1, $telemetry->recordedErrors);
    }

    /** @return array{ResponseInterface, CapturingErrorTelemetryService} */
    private function handle(Throwable $exception): array
    {
        $app = AppFactory::create();
        $app->get('/test', static function () use ($exception): never {
            throw $exception;
        });
        $telemetry = new CapturingErrorTelemetryService();
        ErrorMiddlewareFactory::create($app, false, static fn(): TelemetryService => $telemetry);

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/test');

        return [$app->handle($request), $telemetry];
    }

    /** @return array<mixed> */
    private function payload(ResponseInterface $response): array
    {
        $payload = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($payload);

        return $payload;
    }
}

class CapturingErrorTelemetryService extends TelemetryService
{
    public int $recordedErrors = 0;

    public function __construct() {}

    public function recordSystemError(
        string $level,
        string $channel,
        string $message,
        array $context = [],
        ?Throwable $exception = null
    ): void {
        $this->recordedErrors++;
    }
}
