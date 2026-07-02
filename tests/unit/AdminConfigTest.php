<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Config\AdminConfig;
use PHPUnit\Framework\TestCase;

class AdminConfigTest extends TestCase
{
    public function testEmptyAdminPathDisablesAdminRoutes(): void
    {
        $config = AdminConfig::fromEnv(['ADMIN_PATH' => '']);

        $this->assertFalse($config->isEnabled());
        $this->assertSame('', $config->path);
    }

    public function testAdminPathIsNormalized(): void
    {
        $config = AdminConfig::fromEnv(['ADMIN_PATH' => 'control-panel_42']);

        $this->assertTrue($config->isEnabled());
        $this->assertSame('/control-panel_42', $config->path);
    }

    public function testObviousAdminPathIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AdminConfig::fromEnv(['ADMIN_PATH' => '/admin']);
    }

    public function testReadsSecurityLimits(): void
    {
        $config = AdminConfig::fromEnv([
            'ADMIN_PATH' => '/control-panel-42',
            'ADMIN_SESSION_LIFETIME_SECONDS' => '3600',
            'ADMIN_LOGIN_ATTEMPT_LIMIT' => '4',
            'ADMIN_LOGIN_ATTEMPT_WINDOW_SECONDS' => '600',
        ]);

        $this->assertSame(3600, $config->sessionLifetimeSeconds);
        $this->assertSame(4, $config->loginAttemptLimit);
        $this->assertSame(600, $config->loginAttemptWindowSeconds);
    }
}
