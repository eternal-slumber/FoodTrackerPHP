<?php

declare(strict_types=1);

use App\Admin\Config\AdminConfig;
use App\Admin\Controllers\AdminAuthController;
use App\Admin\Controllers\AdminDashboardController;
use Psr\Container\ContainerInterface;
use Slim\App;

return function (App $app, ContainerInterface $container): void {
    $adminConfig = $container->get(AdminConfig::class);
    if (!$adminConfig->isEnabled()) {
        return;
    }

    $path = $adminConfig->path;
    $app->get($path, [AdminAuthController::class, 'login'])->setName('admin.login');
    $app->get($path . '/dashboard', [AdminDashboardController::class, 'dashboard'])->setName('admin.dashboard');
    $app->get($path . '/dashboard/user-activity', [AdminDashboardController::class, 'userActivity'])->setName('admin.dashboard.user_activity');
    $app->get($path . '/dashboard/meal-activity', [AdminDashboardController::class, 'mealActivity'])->setName('admin.dashboard.meal_activity');
    $app->get($path . '/dashboard/ai-activity', [AdminDashboardController::class, 'aiActivity'])->setName('admin.dashboard.ai_activity');
    $app->get($path . '/dashboard/visits', [AdminDashboardController::class, 'visits'])->setName('admin.dashboard.visits');
    $app->post($path . '/auth', [AdminAuthController::class, 'authenticate'])->setName('admin.auth');
    $app->post($path . '/logout', [AdminAuthController::class, 'logout'])->setName('admin.logout');
};
