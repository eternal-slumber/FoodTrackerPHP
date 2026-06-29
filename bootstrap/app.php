<?php

declare(strict_types=1);

use App\Http\ErrorMiddlewareFactory;
use App\Http\Middleware\TelegramAuthMiddleware;
use Slim\Factory\AppFactory;

$container = require __DIR__ . '/container.php';

AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->add($container->get(TelegramAuthMiddleware::class));

$displayErrorDetails = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
ErrorMiddlewareFactory::create(
    $app,
    $displayErrorDetails,
    fn() => $container->get(App\Services\TelemetryService::class)
);

(require dirname(__DIR__) . '/config/routes.php')($app, $container);

return $app;
