<?php

declare(strict_types=1);

use App\Http\ErrorMiddlewareFactory;
use App\Http\Middleware\TelegramAuthMiddleware;
use RuntimeMentalMap\AutoInstrumentation;
use RuntimeMentalMap\PdoInstrumentation;
use RuntimeMentalMap\Slim\RuntimeMapMiddleware;
use Slim\Factory\AppFactory;

$container = require __DIR__ . '/container.php';
$runtimeMapEnabled = filter_var(
    $_ENV['RUNTIME_MAP_ENABLED'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

if ($runtimeMapEnabled) {
    AutoInstrumentation::register(
        sourceDirectory: dirname(__DIR__) . '/src',
        layers: [
            'Controllers' => 'controller',
            'Services' => 'application',
            'Repositories' => 'infrastructure',
        ],
        namespace: 'App\\',
        exclude: [
            'App\\Services\\TelemetryService',
        ],
    );
    PdoInstrumentation::register();
}

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

if ($runtimeMapEnabled) {
    $app->add(new RuntimeMapMiddleware(
        collectorUrl: $_ENV['RUNTIME_MAP_COLLECTOR_URL']
            ?? 'http://host.docker.internal:9000',
        serviceName: $_ENV['RUNTIME_MAP_SERVICE_NAME']
            ?? 'foodtracker',
    ));
}

(require dirname(__DIR__) . '/config/routes.php')($app, $container);

return $app;
