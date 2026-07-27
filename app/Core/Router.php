<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;

final class Router
{
    /** @var array<string, array{0:class-string, 1:string}> keyed "VERB /path" */
    private array $routes = [];

    private static string $current = '/';

    public function get(string $path, array $action): self
    {
        return $this->add('GET', $path, $action);
    }

    public function post(string $path, array $action): self
    {
        return $this->add('POST', $path, $action);
    }

    /** Any other verb: $router->add('DELETE', '/orders/1', [...]) */
    public function add(string $verb, string $path, array $action): self
    {
        $this->routes[strtoupper($verb) . ' ' . self::normalise($path)] = $action;
        return $this;
    }

    /** Path of the request being rendered — used for nav highlighting and lang links. */
    public static function current(): string
    {
        return self::$current;
    }

    public function dispatch(string $uri): void
    {
        $path = self::normalise(parse_url($uri, PHP_URL_PATH) ?: '/');
        self::$current = $path;

        $verb = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($verb === 'HEAD') {
            $verb = 'GET'; // HEAD is a GET whose body is discarded
        }

        $action = $this->routes["$verb $path"] ?? null;

        if ($action === null) {
            // The path may exist under a different verb — that is 405, not 404.
            $allowed = $this->verbsFor($path);

            if ($allowed !== []) {
                http_response_code(405);
                header('Allow: ' . implode(', ', $allowed));
                $action = [ErrorController::class, 'notAllowed'];
            } else {
                http_response_code(404);
                $action = [ErrorController::class, 'notFound'];
            }
        }

        [$class, $method] = $action;
        (new $class())->{$method}();
    }

    /** Same rule at registration and dispatch, so the two can never disagree. */
    private static function normalise(string $path): string
    {
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /** Which verbs are registered for this path. @return list<string> */
    private function verbsFor(string $path): array
    {
        $verbs = [];
        foreach (array_keys($this->routes) as $key) {
            [$verb, $routePath] = explode(' ', $key, 2);
            if ($routePath === $path) {
                $verbs[] = $verb;
            }
        }
        return $verbs;
    }
}
