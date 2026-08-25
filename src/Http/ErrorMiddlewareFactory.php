<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Services\TelemetryService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Middleware\ErrorMiddleware;
use Throwable;

final class ErrorMiddlewareFactory
{
    /**
     * @param App<ContainerInterface|null> $app
     * @param (callable(): TelemetryService)|null $telemetryFactory
     */
    public static function create(App $app, bool $displayErrorDetails, ?callable $telemetryFactory = null): ErrorMiddleware
    {
        $errorMiddleware = $app->addErrorMiddleware(
            $displayErrorDetails,
            true,
            true
        );

        $errorMiddleware->setDefaultErrorHandler(
            function (
                Request $request,
                Throwable $exception,
                bool $displayErrorDetails
            ) use ($app, $telemetryFactory): Response {
                $isAppException = $exception instanceof AppException;
                $statusCode = $isAppException || $exception instanceof HttpException
                    ? $exception->getCode()
                    : 500;
                $statusCode = $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500;

                if (class_exists(\RuntimeMentalMap\RuntimeMap::class)) {
                    \RuntimeMentalMap\RuntimeMap::recordException($exception);
                }

                $payload = $isAppException
                    ? $exception->toArray()
                    : ['error' => $statusCode === 404 ? 'Not Found' : 'Internal Server Error'];

                if ($displayErrorDetails) {
                    $payload['exception'] = $exception::class;
                    $payload['message'] = $exception->getMessage();
                    $payload['file'] = $exception->getFile();
                    $payload['line'] = $exception->getLine();
                    $payload['trace'] = $exception->getTrace();
                }

                $shouldRecordError = !$exception instanceof ValidationException && $statusCode >= 500;
                if ($shouldRecordError) {
                    error_log(sprintf(
                        'HTTP %d %s %s: %s',
                        $statusCode,
                        $request->getMethod(),
                        (string) $request->getUri(),
                        $exception->getMessage()
                    ));
                }

                if ($telemetryFactory !== null && $shouldRecordError) {
                    try {
                        $telemetryFactory()->recordSystemError(
                            'error',
                            'http',
                            $exception->getMessage(),
                            [
                                'status_code' => $statusCode,
                                'method' => $request->getMethod(),
                                'path' => $request->getUri()->getPath(),
                            ],
                            $exception
                        );
                    } catch (Throwable $telemetryError) {
                        error_log('HTTP error telemetry write failed: ' . $telemetryError->getMessage());
                    }
                }

                return ResponseResponder::json($app->getResponseFactory()->createResponse(), $payload, $statusCode);
            }
        );

        return $errorMiddleware;
    }
}
