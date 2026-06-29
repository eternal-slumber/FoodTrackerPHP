<?php

declare(strict_types=1);

namespace App\Services;

use App\Attributes\RouteAttribute;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Slim\App;

class RouteLoader
{
    public function __construct(
        private App $app,
        private ContainerInterface $container,
        private array $controllerClasses
    ) {}

    public function load(): void
    {
        foreach ($this->controllerClasses as $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(RouteAttribute::class);

                foreach ($attributes as $attribute) {
                    /** @var RouteAttribute $route */
                    $route = $attribute->newInstance();
                    $slimRoute = $this->app->map(
                        [$route->method],
                        $route->path,
                        [$controllerClass, $method->getName()]
                    );

                    if ($route->name !== null) {
                        $slimRoute->setName($route->name);
                    }
                }
            }
        }
    }
}
