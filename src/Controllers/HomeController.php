<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Attributes\RouteAttribute;
use App\Http\ResponseResponder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController
{
    #[RouteAttribute('/', 'GET')]
    public function index(Request $request, Response $response): Response
    {
        $htmlPath = __DIR__ . '/../../public/index.html';
        
        if (file_exists($htmlPath)) {
            return ResponseResponder::html($response, (string)file_get_contents($htmlPath));
        }

        return ResponseResponder::json($response, ['error' => 'HTML file not found'], 404);
    }
}
