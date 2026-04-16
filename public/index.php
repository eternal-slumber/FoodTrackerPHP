<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database;
use App\Core\Router;
use App\Controllers\UserController;
use App\Controllers\AnalyzeController;
use App\Interfaces\AIServiceInterface;
use App\Services\OpenRouterService; // OpenRouter API Service
use App\Services\MealAnalysisService;
use Dotenv\Dotenv;

// 1. Загружаем переменные окружения (.env)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// 2. Инициализируем наш умный контейнер
$container = new Container();

// 3. Настраиваем исключения (то, что рефлексия не соберет сама)

// PDO — это сторонний класс, ему нужны параметры из .env, поэтому создаем его вручную
$container->setInstance(PDO::class, Database::getConnection());

// AIServiceInterface — это интерфейс. 
// Контейнер должен знать, какой именно класс создать, когда его просят.
$container->set(AIServiceInterface::class, function($c) {
    // Внедряем ключ API напрямую в конструктор сервиса
    return new OpenRouterService($_ENV['OPENROUTER_API_KEY']);
});

// Регистрируем MealAnalysisService
$container->set(MealAnalysisService::class, function($c) {
    $uploadPath = dirname(__DIR__) . '/storage/uploads/';
    return new MealAnalysisService(
        $c->get(AIServiceInterface::class),
        $uploadPath
    );
});

// 4. Инициализируем роутер и передаем ему контейнер
$router = new Router($container);

// 5. Определяем маршруты (Routes)
// Заметь: мы просто указываем имена классов. 
// Контейнер сам поймет, что AnalyzeController нужен PDO и AIServiceInterface.
$router->add('GET', '/', 'App\Controllers\HomeController', 'index');
$router->add('GET', '/api/user-status', UserController::class, 'status');
$router->add('GET', '/api/progress', UserController::class, 'progress');
$router->add('POST', '/api/register', UserController::class, 'register');
$router->add('POST', '/api/upload', AnalyzeController::class, 'upload');
$router->add('GET', '/api/history', AnalyzeController::class, 'history');
$router->add('POST', '/api/delete-meal', AnalyzeController::class, 'deleteMeal');
$router->add('POST', '/api/delete-profile', UserController::class, 'delete');

// 6. Запускаем приложение
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}