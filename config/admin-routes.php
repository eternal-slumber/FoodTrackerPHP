<?php

declare(strict_types=1);

use App\Admin\Config\AdminConfig;
use App\Admin\Controllers\AdminAuthController;
use App\Admin\Controllers\AdminDashboardController;
use App\Admin\Middleware\AdminAuthMiddleware;
use App\Admin\Middleware\AdminCsrfMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app, ContainerInterface $container): void {
    $adminConfig = $container->get(AdminConfig::class);
    if (!$adminConfig->isEnabled()) {
        return;
    }

    $path = $adminConfig->path;
    $authMiddleware = $container->get(AdminAuthMiddleware::class);
    $csrfMiddleware = $container->get(AdminCsrfMiddleware::class);

    $app->get($path, [AdminAuthController::class, 'login'])->setName('admin.login');
    $app->post($path . '/auth', [AdminAuthController::class, 'authenticate'])
        ->add($csrfMiddleware)
        ->setName('admin.auth');
    $app->post($path . '/logout', [AdminAuthController::class, 'logout'])
        ->add($authMiddleware)
        ->add($csrfMiddleware)
        ->setName('admin.logout');

    $dashboard = $app->group($path . '/dashboard', function (RouteCollectorProxy $group): void {
        $group->get('', [AdminDashboardController::class, 'dashboard'])->setName('admin.dashboard');
        $group->get('/user-activity', [AdminDashboardController::class, 'userActivity'])->setName('admin.dashboard.user_activity');
        $group->get('/meal-activity', [AdminDashboardController::class, 'mealActivity'])->setName('admin.dashboard.meal_activity');
        $group->get('/ai-activity', [AdminDashboardController::class, 'aiActivity'])->setName('admin.dashboard.ai_activity');
        $group->get('/system-logs', [AdminDashboardController::class, 'systemLogs'])->setName('admin.dashboard.system_logs');
        $group->get('/visits', [AdminDashboardController::class, 'visits'])->setName('admin.dashboard.visits');
    });
    $dashboard->add($authMiddleware);
};
