<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function __construct(
        private readonly Container $container
    ) {}

    // Регистрируем маршрут: метод, путь и какой контроллер/метод вызвать
    public function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'controller' => $controller,
            'action'     => $action
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        // Очищаем URI от параметров типа ?id=1
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                // достаем контроллер из контейнера (со всеми его зависимостями!)
                $controller = $this->container->get($route['controller']);
                $action = $route['action'];

                // Вызываем метод и выводим результат
                echo $controller->$action();
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}