<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\View\ViewRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ViewRendererTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = sys_get_temp_dir() . '/foodtracker-views-' . bin2hex(random_bytes(6));
        mkdir($this->viewsPath . '/partials', 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->viewsPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->viewsPath);
    }

    public function testRenderExpandsNestedPartialsAndReplacements(): void
    {
        file_put_contents($this->viewsPath . '/page.html', '<main>{{> partials/card.html}}</main>');
        file_put_contents($this->viewsPath . '/partials/card.html', '<article>{{title}}{{> partials/meta.html}}</article>');
        file_put_contents($this->viewsPath . '/partials/meta.html', '<small>{{meta}}</small>');

        $renderer = new ViewRenderer($this->viewsPath);

        $this->assertSame(
            '<main><article>План<small>Сегодня</small></article></main>',
            $renderer->render('page.html', ['title' => 'План', 'meta' => 'Сегодня'])
        );
    }

    public function testRenderRejectsCircularPartialReferences(): void
    {
        file_put_contents($this->viewsPath . '/page.html', '{{> partials/card.html}}');
        file_put_contents($this->viewsPath . '/partials/card.html', '{{> page.html}}');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular view partial reference');

        (new ViewRenderer($this->viewsPath))->render('page.html');
    }

    public function testRenderRejectsPathTraversalInPartial(): void
    {
        file_put_contents($this->viewsPath . '/page.html', '{{> ../secret.html}}');

        $this->expectException(InvalidArgumentException::class);

        (new ViewRenderer($this->viewsPath))->render('page.html');
    }
}

