<?php

declare(strict_types=1);

use App\Controllers\AnalyzeController;
use App\Controllers\AiUsageController;
use App\Controllers\HomeController;
use App\Controllers\TelegramBotController;
use App\Controllers\UserController;
use App\Services\RouteLoader;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {
    $adminEnabled = filter_var($_ENV['ADMIN_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $adminOnly = filter_var($_ENV['ADMIN_ONLY'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$adminOnly) {
        $routeLoader = new RouteLoader($app, $container, [
            HomeController::class,
            UserController::class,
            AiUsageController::class,
            AnalyzeController::class,
            TelegramBotController::class,
        ]);

        $routeLoader->load();
    }

    if ($adminEnabled) {
        (require __DIR__ . '/admin-routes.php')($app, $container);
    }
};
