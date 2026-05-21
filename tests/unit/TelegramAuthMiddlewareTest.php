<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\CurrentUser;
use App\Auth\TelegramAuthService;
use App\Http\Middleware\TelegramAuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class TelegramAuthMiddlewareTest extends TestCase
{
    public function testProductionRejectsMissingTelegramInitData(): void
    {
        $middleware = new TelegramAuthMiddleware(
            new TelegramAuthService('test-bot-token'),
            new ResponseFactory(),
            'production',
            true,
            100001,
            'dev_user'
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/user-status');
        $response = $middleware->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testLocalDevAuthAllowsMissingTelegramInitData(): void
    {
        $middleware = new TelegramAuthMiddleware(
            new TelegramAuthService('test-bot-token'),
            new ResponseFactory(),
            'local',
            true,
            100001,
            'dev_user'
        );

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/user-status');
        $response = $middleware->process($request, $this->handler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('100001', (string)$response->getBody());
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $currentUser = $request->getAttribute(TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE);
                $response = (new ResponseFactory())->createResponse();

                if (!$currentUser instanceof CurrentUser) {
                    return $response->withStatus(500);
                }

                $response->getBody()->write((string)$currentUser->telegramId);

                return $response;
            }
        };
    }
}
