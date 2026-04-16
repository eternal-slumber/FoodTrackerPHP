<?php

declare(strict_types=1);

namespace App\Core;

use Exception;
use ReflectionClass;
use ReflectionNamedType;

class Container
{
    private array $services = [];
    private array $instances = []; // Выносим статику в свойство класса

    public function set(string $name, callable $closure): void
    {
        $this->services[$name] = $closure;
    }

    // Метод для записи уже готовых объектов (например, PDO)
    public function setInstance(string $name, $instance): void
    {
        $this->instances[$name] = $instance;
    }

    public function get(string $name)
    {
        // 1. Если объект уже создан (Singleton), возвращаем его
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // 2. Если есть ручной "рецепт" через set(), выполняем его
        if (isset($this->services[$name])) {
            $this->instances[$name] = $this->services[$name]($this);
            return $this->instances[$name];
        }

        // 3. Если рецепта нет — включаем автосвязывание (Рефлексию)
        return $this->resolve($name);
    }

    private function resolve(string $name)
    {
        $reflectionClass = new ReflectionClass($name);

        if (!$reflectionClass->isInstantiable()) {
            throw new Exception("Класс {$name} не может быть создан.");
        }

        $constructor = $reflectionClass->getConstructor();

        // Если конструктора нет, просто создаем объект
        if (is_null($constructor)) {
            return new $name();
        }

        // Получаем параметры конструктора
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new Exception("Невозможно разрешить встроенный тип (string/int) в классе {$name}");
            }

            // РЕКУРСИЯ: запрашиваем зависимость у этого же контейнера
            $dependencies[] = $this->get($type->getName());
        }

        // Создаем объект с зависимостями
        $instance = $reflectionClass->newInstanceArgs($dependencies);
        $this->instances[$name] = $instance;

        return $instance;
    }
}