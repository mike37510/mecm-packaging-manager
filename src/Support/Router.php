<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lightweight router with named parameters ({id}).
 */
final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, regex: string, handler: callable|array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add(['POST'], $pattern, $handler, $middleware);
    }

    /**
     * @param list<string> $methods
     * @param callable|array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function add(array $methods, string $pattern, callable|array $handler, array $middleware = []): void
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @return array{handler: callable|array{0: class-string, 1: string}, params: array<string, string>, middleware: list<string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return [
                'handler' => $route['handler'],
                'params' => $params,
                'middleware' => $route['middleware'],
            ];
        }

        return null;
    }
}
