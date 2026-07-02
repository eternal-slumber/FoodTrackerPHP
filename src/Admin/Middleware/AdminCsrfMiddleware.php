<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Admin\Auth\AdminSession;
use App\Http\ResponseResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AdminCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AdminSession $adminSession,
        private readonly ResponseFactory $responseFactory
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('X-CSRF-Token');
        if ($this->adminSession->isValidCsrfToken($token)) {
            return $handler->handle($request);
        }

        return ResponseResponder::json($this->responseFactory->createResponse(), [
            'status' => 'error',
            'message' => 'Сессия формы устарела. Обновите страницу.',
        ], 403);
    }
}
