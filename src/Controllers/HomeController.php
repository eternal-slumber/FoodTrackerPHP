<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController
{
    public function index(): void
    {
        $htmlPath = __DIR__ . '/../../public/index.html';
        
        if (file_exists($htmlPath)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($htmlPath);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'HTML file not found']);
        }
    }
}
