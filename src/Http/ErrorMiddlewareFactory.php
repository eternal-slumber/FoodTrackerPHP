<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Middleware\ErrorMiddleware;
use Throwable;

final class ErrorMiddlewareFactory
{
    public static function create(App $app, bool $displayErrorDetails): ErrorMiddleware
    {
        $errorMiddleware = $app->addErrorMiddleware(
            $displayErrorDetails,
            true,
            true
        );

        $errorMiddleware->setDefaultErrorHandler(
            static function (
                Request $request,
                Throwable $exception,
                bool $displayErrorDetails
            ) use ($app): Response {
                $statusCode = $exception instanceof HttpException ? $exception->getCode() : 500;
                $statusCode = $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500;

                $payload = [
                    'error' => $statusCode === 404 ? 'Not Found' : 'Internal Server Error',
                ];

                if ($displayErrorDetails) {
                    $payload['message'] = $exception->getMessage();
                }

                error_log(sprintf(
                    'HTTP %d %s %s: %s',
                    $statusCode,
                    $request->getMethod(),
                    (string) $request->getUri(),
                    $exception->getMessage()
                ));

                return ResponseResponder::json($app->getResponseFactory()->createResponse(), $payload, $statusCode);
            }
        );

        return $errorMiddleware;
    }
}
