<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Auth\AdminLoginGuard;
use App\Admin\Config\AdminConfig;
use App\Admin\Repositories\AdminLoginAttemptRepository;
use PHPUnit\Framework\TestCase;

class AdminLoginGuardTest extends TestCase
{
    public function testUsesHashedScopeAndConfiguredLimits(): void
    {
        $rateLimiter = new FakeAdminLoginAttemptRepository();
        $config = new AdminConfig('/control-panel-42', 1800, 4, 600);
        $guard = new AdminLoginGuard($config, $rateLimiter);

        $status = $guard->status('admin-login', '127.0.0.1');
        $guard->registerFailure('admin-login', '127.0.0.1');

        $this->assertFalse($status['blocked']);
        $this->assertSame(321, $status['retry_after']);
        $this->assertSame(64, strlen($rateLimiter->scope));
        $this->assertStringNotContainsString('admin-login', $rateLimiter->scope);
        $this->assertSame('admin_login_failed', $rateLimiter->action);
        $this->assertSame(4, $rateLimiter->limit);
        $this->assertSame(600, $rateLimiter->windowSeconds);
        $this->assertSame(1, $rateLimiter->consumeCalls);
    }
}

class FakeAdminLoginAttemptRepository extends AdminLoginAttemptRepository
{
    public string $scope = '';
    public string $action = '';
    public int $limit = 0;
    public int $windowSeconds = 0;
    public int $consumeCalls = 0;

    public function __construct() {}

    public function status(string $scope, string $action, int $limit, int $windowSeconds): array
    {
        $this->remember($scope, $action, $limit, $windowSeconds);

        return [
            'remaining' => max(0, $limit - 1),
            'resets_in_seconds' => 321,
        ];
    }

    public function consume(string $scope, string $action, int $limit, int $windowSeconds): bool
    {
        $this->remember($scope, $action, $limit, $windowSeconds);
        $this->consumeCalls++;

        return true;
    }

    private function remember(string $scope, string $action, int $limit, int $windowSeconds): void
    {
        $this->scope = $scope;
        $this->action = $action;
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
    }
}
