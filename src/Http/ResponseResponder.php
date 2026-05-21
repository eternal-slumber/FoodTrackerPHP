<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

class ResponseResponder
{
    public static function json(
        ResponseInterface $response,
        array $payload,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    public static function html(
        ResponseInterface $response,
        string $html,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($status);
    }
}
