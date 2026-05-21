<?php

declare(strict_types=1);

use App\Controllers\AnalyzeController;
use App\Controllers\HomeController;
use App\Controllers\UserController;
use App\Services\RouteLoader;
use Slim\App;

return static function (App $app): void {
    $routeLoader = new RouteLoader($app, [
        HomeController::class,
        UserController::class,
        AnalyzeController::class,
    ]);

    $routeLoader->load();
};
