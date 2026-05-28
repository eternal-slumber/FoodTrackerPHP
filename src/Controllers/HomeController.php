<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\Config\TelegramAuthConfig;
use App\Http\ResponseResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController
{
    public function __construct(private readonly TelegramAuthConfig $telegramAuthConfig) {}

    #[RouteAttribute('/', 'GET')]
    public function index(Request $request, Response $response): Response
    {
        $htmlPath = __DIR__ . '/../../public/index.html';
        
        if (file_exists($htmlPath)) {
            return ResponseResponder::html(
                $response,
                $this->injectFrontendConfig((string)file_get_contents($htmlPath))
            );
        }

        return ResponseResponder::json($response, ['error' => 'HTML file not found'], 404);
    }

    private function injectFrontendConfig(string $html): string
    {
        if (!$this->shouldExposeDevConfig()) {
            return $html;
        }

        $script = sprintf(
            "\n<script>window.__APP_CONFIG__ = %s;</script>\n",
            json_encode($this->frontendConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        );

        if (str_contains($html, '</head>')) {
            return str_replace('</head>', $script . '</head>', $html);
        }

        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $script . '</body>', $html);
        }

        return $html . $script;
    }

    private function shouldExposeDevConfig(): bool
    {
        return $this->telegramAuthConfig->appEnv === 'local'
            && $this->telegramAuthConfig->devAuthEnabled
            && $this->telegramAuthConfig->devUserId > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function frontendConfig(): array
    {
        $username = trim($this->telegramAuthConfig->devUsername);

        return [
            'appEnv' => $this->telegramAuthConfig->appEnv,
            'devTelegramUser' => [
                'id' => $this->telegramAuthConfig->devUserId,
                'username' => $username,
                'firstName' => $username !== '' ? $username : 'Dev',
            ],
        ];
    }
}
