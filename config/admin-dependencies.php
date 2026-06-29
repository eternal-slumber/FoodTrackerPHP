<?php

declare(strict_types=1);

use App\Admin\Auth\AdminSession;
use App\Admin\Config\AdminConfig;
use App\Admin\Controllers\AdminAuthController;
use App\Admin\Controllers\AdminDashboardController;
use App\Admin\Repositories\AdminUserRepository;
use App\Admin\Services\AppHealthService;
use App\Admin\Services\DashboardStatsService;
use App\Admin\View\AdminDashboardFormatter;
use App\Admin\View\AdminDashboardPageRenderer;

use function DI\autowire;

return [
    AdminConfig::class => fn(): AdminConfig => AdminConfig::fromEnv($_ENV),
    AdminSession::class => autowire(),
    AdminUserRepository::class => autowire(),
    AppHealthService::class => fn(): AppHealthService => new AppHealthService(
        (string)($_ENV['ADMIN_APP_HEALTH_HOST'] ?? 'app'),
        (int)($_ENV['ADMIN_APP_HEALTH_PORT'] ?? 9000)
    ),
    DashboardStatsService::class => autowire(),
    AdminDashboardFormatter::class => autowire(),
    AdminDashboardPageRenderer::class => autowire(),
    AdminAuthController::class => autowire(),
    AdminDashboardController::class => autowire(),
];
