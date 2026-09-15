<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Extension points core code calls without knowing which modules (if any)
 * are listening — the mechanism that lets a module like Warehouse plug
 * fields into the Products form/table/save-flow without ProductController
 * or products.php ever mentioning "Warehouse" by name.
 *
 * Deliberately just this: a static array + foreach. No priority, no off(),
 * no wildcard points. Add ceremony when a real second consumer needs it,
 * not before — see handoff.md for the points currently in use.
 */
final class Hooks
{
    /** @var array<string, list<callable>> */
    private static array $callbacks = [];

    public static function on(string $point, callable $cb): void
    {
        self::$callbacks[$point][] = $cb;
    }

    /**
     * Every listener's raw return value, in registration order. The shape of
     * that return value is a convention between the core call site and
     * whatever modules listen at that point (documented per point where
     * it's called) — Hooks itself doesn't care what's returned.
     */
    public static function call(string $point, array $context = []): array
    {
        return array_map(static fn(callable $cb) => $cb($context), self::$callbacks[$point] ?? []);
    }

    /** For render.* points: every listener must return a string; concatenated in order. */
    public static function render(string $point, array $context = []): string
    {
        return implode('', array_map('strval', self::call($point, $context)));
    }

    /**
     * For *.data points: every listener returns a map keyed by module code,
     * merged into one array so a view can read $moduleData['Warehouse'][$id]
     * without knowing which modules answered.
     *
     * This is the point the first module system never had, and its absence is
     * why the removed InvoiceWorkflow was hardcoded into InvoiceController:
     * a listing needs its extra column fetched once for the whole page, and a
     * render-only hook would have left each module querying per row (4.113).
     *
     * @return array<string, mixed>
     */
    public static function merge(string $point, array $context = []): array
    {
        $out = [];
        foreach (self::call($point, $context) as $result) {
            if (is_array($result)) {
                $out += $result;
            }
        }

        return $out;
    }
}
