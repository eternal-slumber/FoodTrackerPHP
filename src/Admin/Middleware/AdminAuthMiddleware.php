<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Admin\Auth\AdminSession;
use App\Admin\Config\AdminConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AdminSession $adminSession,
        private readonly AdminConfig $adminConfig,
        private readonly ResponseFactory $responseFactory
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->adminSession->currentAdminId() !== null) {
            return $handler->handle($request);
        }

        return $this->responseFactory
            ->createResponse(302)
            ->withHeader('Location', $this->adminConfig->path);
    }
}
