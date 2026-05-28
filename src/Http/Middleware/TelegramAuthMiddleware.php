<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\CurrentUser;
use App\Auth\TelegramAuthService;
use App\Exceptions\ValidationException;
use App\Http\ResponseResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class TelegramAuthMiddleware implements MiddlewareInterface
{
    public const CURRENT_USER_ATTRIBUTE = 'current_user';

    public function __construct(
        private readonly TelegramAuthService $telegramAuthService,
        private readonly ResponseFactory $responseFactory,
        private readonly string $appEnv = 'production',
        private readonly bool $devAuthEnabled = false,
        private readonly int $devUserId = 100001,
        private readonly string $devUsername = 'dev_user'
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, '/api/')) {
            return $handler->handle($request);
        }

        $initData = $request->getHeaderLine('X-Telegram-Init-Data');
        if ($initData === '') {
            if ($this->isDevAuthAllowed()) {
                return $handler->handle($request->withAttribute(
                    self::CURRENT_USER_ATTRIBUTE,
                    new CurrentUser(
                        telegramId: $this->devUserId,
                        username: $this->devUsername,
                        firstName: $this->devUsername !== '' ? $this->devUsername : 'Dev'
                    )
                ));
            }

            return ResponseResponder::json(
                $this->responseFactory->createResponse(),
                ['error' => 'Unauthorized', 'message' => 'Missing Telegram auth data'],
                401
            );
        }

        try {
            $currentUser = $this->telegramAuthService->validateInitData($initData);
        } catch (ValidationException $e) {
            return ResponseResponder::json(
                $this->responseFactory->createResponse(),
                ['error' => 'Unauthorized', 'message' => 'Invalid Telegram auth data'],
                401
            );
        }

        return $handler->handle($request->withAttribute(self::CURRENT_USER_ATTRIBUTE, $currentUser));
    }

    private function isDevAuthAllowed(): bool
    {
        return $this->appEnv === 'local'
            && $this->devAuthEnabled
            && $this->devUserId > 0;
    }
}
