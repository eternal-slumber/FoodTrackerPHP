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
}
