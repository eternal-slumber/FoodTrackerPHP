<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\Http\ResponseResponder;
use App\Services\TrainerShareService;
use App\View\ViewRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TrainerShareController
{
    public function __construct(
        private readonly TrainerShareService $trainerShare,
        private readonly ViewRenderer $views
    ) {}

    #[RouteAttribute('/s/{token}', 'GET')]
    public function show(Request $request, Response $response, array $args): Response
    {
        $data = $this->trainerShare->getPublicDiary((string)($args['token'] ?? ''));
        $html = $this->views->render('trainer/share.html', [
            'frontend_asset_version' => $this->assetVersion(),
            'share_state' => $data === null ? 'unavailable' : 'ready',
            'share_data_json' => $data === null ? '{}' : json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            ),
        ]);

        return ResponseResponder::html($response, $html, $data === null ? 404 : 200)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function assetVersion(): string
    {
        $path = dirname(__DIR__, 2) . '/public/assets/app/dist/trainer-share.js';
        $modifiedAt = is_file($path) ? filemtime($path) : false;

        return $modifiedAt === false ? 'unbuilt' : (string)$modifiedAt;
    }
}
