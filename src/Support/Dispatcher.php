<?php

declare(strict_types=1);

namespace App\Support;

use App\Security\Auth;
use App\Security\Csrf;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Dispatches matched routes to controller methods with DI.
 */
final class Dispatcher
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<string, string> $params
     */
    public function dispatch(callable|array $handler, array $params, Request $request): void
    {
        if (is_callable($handler) && !is_array($handler)) {
            $handler($request, ...array_values($params));
            return;
        }

        /** @var array{0: class-string, 1: string} $handler */
        [$class, $method] = $handler;

        $controller = $this->resolveController($class);
        $args = $this->resolveMethodArgs($controller, $method, $params, $request);
        $controller->{$method}(...$args);
    }

    private function resolveController(string $class): object
    {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        // Controllers extending BaseController need View, Auth, Csrf
        if (is_subclass_of($class, \App\Controllers\BaseController::class)) {
            return new $class(
                $this->container->get(\App\Support\View::class),
                $this->container->get(Auth::class),
                $this->container->get(Csrf::class),
            );
        }

        return new $class();
    }

    /**
     * @param array<string, string> $params
     * @return list<mixed>
     */
    private function resolveMethodArgs(object $controller, string $method, array $params, Request $request): array
    {
        $ref = new ReflectionMethod($controller, $method);
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($typeName === Request::class) {
                    $args[] = $request;
                    continue;
                }
                if ($this->container->has($typeName)) {
                    $args[] = $this->container->get($typeName);
                    continue;
                }
                if (class_exists($typeName)) {
                    $args[] = $this->container->has($typeName)
                        ? $this->container->get($typeName)
                        : new $typeName();
                    continue;
                }
            }

            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Unable to resolve parameter $%s for %s::%s',
                $name,
                $controller::class,
                $method
            ));
        }

        return $args;
    }
}
