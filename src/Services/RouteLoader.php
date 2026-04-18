<?php

declare(strict_types=1);

namespace App\Services;

use App\Attributes\RouteAttribute;
use App\Core\Router;
use ReflectionClass;
use ReflectionMethod;

class RouteLoader
{
    public function __construct(
        private Router $router,
        private array $controllerClasses
    ) {}

    public function load(): void
    {
        foreach ($this->controllerClasses as $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class);

                foreach ($attributes as $attribute) {
                    /** 
                     * @var RouteAttribute $route 
                     * */
                    $route = $attribute->newInstance();
                    $this->router->add($route->method, $route->path, $controllerClass, $method->getName());
                }
            }
        }
    }
}
