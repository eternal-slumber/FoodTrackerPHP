<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\TelegramAuthConfig;
use App\Controllers\HomeController;
use App\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class HomeControllerTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . '/foodtracker-home-' . bin2hex(random_bytes(6));
        mkdir($this->rootPath . '/views/app', 0777, true);
        mkdir($this->rootPath . '/public/assets/app/dist', 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->rootPath);
    }

    public function testUsesLatestFrontendAssetModificationTimeAsVersion(): void
    {
        file_put_contents(
            $this->rootPath . '/views/app/home.html',
            '<link href="/assets/app/dist/app.css?v={{frontend_asset_version}}">'
            . '<script src="/assets/app/dist/app.js?v={{frontend_asset_version}}"></script>'
        );
        file_put_contents($this->rootPath . '/public/assets/app/dist/app.css', 'body{}');
        file_put_contents($this->rootPath . '/public/assets/app/dist/app.js', 'void 0;');
        touch($this->rootPath . '/public/assets/app/dist/app.css', 1_700_000_000);
        touch($this->rootPath . '/public/assets/app/dist/app.js', 1_700_000_100);

        $controller = new HomeController(
            $this->productionTelegramConfig(),
            new ViewRenderer($this->rootPath . '/views'),
            $this->rootPath . '/public'
        );
        $response = $controller->index(
            (new ServerRequestFactory())->createServerRequest('GET', '/'),
            (new ResponseFactory())->createResponse()
        );
        $html = (string)$response->getBody();

        $this->assertStringContainsString('app.css?v=1700000100', $html);
        $this->assertStringContainsString('app.js?v=1700000100', $html);
        $this->assertStringNotContainsString('{{frontend_asset_version}}', $html);
    }

    private function productionTelegramConfig(): TelegramAuthConfig
    {
        return new TelegramAuthConfig(
            botToken: 'test-token',
            maxAgeSeconds: 86400,
            appEnv: 'production',
            devAuthEnabled: false,
            devUserId: 0,
            devUsername: ''
        );
    }
}
