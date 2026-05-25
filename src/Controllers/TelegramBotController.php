<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\Http\ResponseResponder;
use App\Telegram\TelegramBotService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TelegramBotController
{
    public function __construct(private readonly TelegramBotService $bot) {}

    #[RouteAttribute('/telegram/webhook', 'POST')]
    public function webhook(Request $request, Response $response): Response
    {
        if (!$this->bot->isWebhookAuthorized($request->getHeaderLine('X-Telegram-Bot-Api-Secret-Token'))) {
            return ResponseResponder::json($response, ['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            $payload = json_decode((string)$request->getBody(), true);
        }

        if (!is_array($payload)) {
            return ResponseResponder::json($response, ['ok' => false, 'error' => 'Invalid payload'], 400);
        }

        try {
            $this->bot->handleUpdate($payload);
        } catch (\Throwable $e) {
            error_log('Telegram bot webhook error: ' . $e->getMessage());
        }

        return ResponseResponder::json($response, ['ok' => true]);
    }
}
