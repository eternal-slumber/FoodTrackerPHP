<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Auth\AdminSession;
use App\Admin\Config\AdminConfig;
use App\Admin\Middleware\AdminAuthMiddleware;
use App\Admin\Middleware\AdminCsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class AdminSecurityMiddlewareTest extends TestCase
{
    public function testAuthMiddlewareRedirectsAnonymousRequest(): void
    {
        $session = new FakeMiddlewareAdminSession(null, false);
        $middleware = new AdminAuthMiddleware(
            $session,
            new AdminConfig('/control-panel-42'),
            new ResponseFactory()
        );

        $response = $middleware->process($this->request(), new SuccessfulRequestHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/control-panel-42', $response->getHeaderLine('Location'));
    }

    public function testAuthMiddlewarePassesAuthenticatedRequest(): void
    {
        $middleware = new AdminAuthMiddleware(
            new FakeMiddlewareAdminSession(7, false),
            new AdminConfig('/control-panel-42'),
            new ResponseFactory()
        );

        $response = $middleware->process($this->request(), new SuccessfulRequestHandler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCsrfMiddlewareRejectsInvalidToken(): void
    {
        $middleware = new AdminCsrfMiddleware(
            new FakeMiddlewareAdminSession(7, false),
            new ResponseFactory()
        );

        $response = $middleware->process($this->request(), new SuccessfulRequestHandler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Сессия формы устарела', (string)$response->getBody());
    }

    public function testCsrfMiddlewarePassesValidToken(): void
    {
        $middleware = new AdminCsrfMiddleware(
            new FakeMiddlewareAdminSession(7, true),
            new ResponseFactory()
        );
        $request = $this->request()->withHeader('X-CSRF-Token', 'valid-token');

        $response = $middleware->process($request, new SuccessfulRequestHandler());

        $this->assertSame(204, $response->getStatusCode());
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/control-panel-42/dashboard');
    }
}

class FakeMiddlewareAdminSession extends AdminSession
{
    public function __construct(
        private readonly ?int $adminId,
        private readonly bool $validCsrfToken
    ) {}

    public function currentAdminId(): ?int
    {
        return $this->adminId;
    }

    public function isValidCsrfToken(string $token): bool
    {
        return $this->validCsrfToken && $token === 'valid-token';
    }
}

class SuccessfulRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return (new ResponseFactory())->createResponse(204);
    }
}
