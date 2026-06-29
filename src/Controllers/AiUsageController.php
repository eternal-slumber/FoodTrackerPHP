<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\CurrentUser;
use App\Attributes\RouteAttribute;
use App\Http\Middleware\TelegramAuthMiddleware;
use App\Http\ResponseResponder;
use App\Services\AiQuotaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AiUsageController
{
    public function __construct(private readonly AiQuotaService $aiQuota) {}

    #[RouteAttribute('/api/ai-usage', 'GET')]
    public function show(Request $request, Response $response): Response
    {
        $currentUser = $request->getAttribute(TelegramAuthMiddleware::CURRENT_USER_ATTRIBUTE);
        if (!$currentUser instanceof CurrentUser) {
            return ResponseResponder::json($response, [
                'status' => 'error',
                'message' => 'Authenticated Telegram user is missing',
            ], 401);
        }

        return ResponseResponder::json($response, [
            'status' => 'success',
            'data' => $this->aiQuota->getUsageForTelegramUser($currentUser->telegramId),
        ]);
    }
}
