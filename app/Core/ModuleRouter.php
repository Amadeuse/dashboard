<?php

declare(strict_types=1);

namespace App\Core;

/**
 * What a module gets instead of the real Router — every path it registers is
 * forced under /m/<code>/ (4.113).
 *
 * The point is collision-proofing, not privilege: two modules can never claim
 * the same URL, and any request's owning module is readable straight from the
 * path. It is deliberately NOT a security boundary — a module's controller
 * runs with the same Db and Auth access core code has, whatever its URL. On
 * this platform a module is trusted code, like a plugin; the review before
 * installing one is what stands in for a sandbox.
 *
 * Enforced here rather than asked for by convention, because a convention is
 * exactly what the first module system relied on, and modules ignored it.
 */
final class ModuleRouter
{
    public function __construct(
        private readonly Router $router,
        private readonly string $code,
    ) {
    }

    public function get(string $path, array $handler): void
    {
        $this->router->get($this->prefix($path), $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->router->post($this->prefix($path), $handler);
    }

    public function add(string $method, string $path, array $handler): void
    {
        $this->router->add($method, $this->prefix($path), $handler);
    }

    /** The module's own base path — for building links in its views. */
    public function base(): string
    {
        return '/m/' . strtolower($this->code);
    }

    /**
     * '/things' and 'things' both become '/m/<code>/things'; '/' becomes the
     * base itself. A module cannot escape the prefix: the result is built
     * from its own code, and whatever it passed is appended as a single
     * cleaned segment path, never used as the root.
     */
    private function prefix(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#/\.\.(?=/|$)#', '', $path) ?? '/';
        $path = rtrim($path, '/');

        return $this->base() . $path;
    }
}
