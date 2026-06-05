<?php

declare(strict_types=1);

use App\Controllers\AnalyzeController;
use App\Controllers\HomeController;
use App\Controllers\TelegramBotController;
use App\Controllers\UserController;
use App\Services\RouteLoader;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {
    $routeLoader = new RouteLoader($app, $container, [
        HomeController::class,
        UserController::class,
        AnalyzeController::class,
        TelegramBotController::class,
    ]);

    $routeLoader->load();
};
