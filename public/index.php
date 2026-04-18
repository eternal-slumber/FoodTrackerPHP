<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Container;
use App\Core\Database;
use App\Core\Router;
use App\Interfaces\AIServiceInterface;
use App\Services\OpenRouterService; // OpenRouter API Service(Nvidia)
use App\Services\MealAnalysisService;
use Dotenv\Dotenv;

// 1.Загружаем переменные окружения (.env)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// 2.Инициализируем контейнер
$container = new Container();

// 3.Настраиваем исключения (то, что рефлексия не соберет сама)

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

// 4.Инициализируем роутер и передаем ему контейнер
$router = new Router($container);

// 5.Загружаем маршруты через атрибуты
$routeLoader = new \App\Services\RouteLoader($router, [
    \App\Controllers\HomeController::class,
    \App\Controllers\UserController::class,
    \App\Controllers\AnalyzeController::class,
]);
$routeLoader->load();

// 6. Запуск приложения
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}