<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The module system's registry — see handoff.md 4.113 and the author's guide
 * at /help/modules for the full contract. The rule everything here serves:
 *
 *     Installing a module changes no core file.
 *
 * A module is one folder under app/Modules/<Code>/ carrying everything it
 * needs — its own manifest, routes, translations, assets, views, migrations
 * and uninstall SQL. The first module system failed that rule (its text
 * lived in app/lang/*.php and its markup inside core views), which is why it
 * was removed whole in 4.112 rather than patched.
 *
 * Two states, deliberately separate:
 *   installed — the files are on this server and its migrations have run.
 *               One server-wide act, admin only.
 *   enabled   — one tenant has it switched on (`module_tenants`). A customer
 *               who buys a module gets it for their own `ruler` only.
 */
final class ModuleRegistry
{
    /** Codes whose Module.php threw while loading — auto-disabled for this request. */
    private static array $failed = [];

    private static ?array $installed = null;
    private static array $enabledByTenant = [];

    /**
     * A module code is also a directory name and a PHP namespace segment, so
     * it is restricted at every entry point rather than trusted: letters and
     * digits, starting with a letter. That alone makes "../../public" and
     * every other traversal attempt un-representable.
     */
    public static function isValidCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9]{0,63}$/', $code);
    }

    public static function dir(string $code): string
    {
        return APP_PATH . '/Modules/' . $code;
    }

    /**
     * Every app/Modules/*\/module.json on disk, keyed by code. Filesystem +
     * JSON cost — for the admin page only, never the per-request path (see
     * enabledCodes()). A manifest that doesn't parse is skipped rather than
     * thrown: one broken third-party folder must not take the page down.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function discover(): array
    {
        $modules = [];
        foreach (glob(APP_PATH . '/Modules/*/module.json') as $file) {
            $code = basename(dirname($file));
            if (!self::isValidCode($code)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($file), true);
            if (is_array($manifest)) {
                $modules[$code] = $manifest;
            }
        }

        return $modules;
    }

    /** Installed rows from `modules`, keyed by code. @return array<string, array<string, mixed>> */
    public static function installed(): array
    {
        if (self::$installed === null) {
            self::$installed = [];
            foreach (Db::all('SELECT * FROM modules') as $row) {
                self::$installed[$row['code']] = $row;
            }
        }

        return self::$installed;
    }

    public static function isInstalled(string $code): bool
    {
        return isset(self::installed()[$code]);
    }

    public static function isEnabled(string $code, ?int $ruler = null): bool
    {
        return in_array($code, self::enabledCodes($ruler), true);
    }

    /**
     * The tenant whose modules apply right now, or null when there isn't one.
     *
     * Auth::tenantId() is not safe to call here: with no session it redirects
     * to /login, and for a SuperUser who isn't impersonating it redirects to
     * /superuser — either would turn the module loop in index.php, which runs
     * on *every* request, into a redirect loop on exactly those two pages.
     * This asks the same questions without ever redirecting.
     */
    private static function currentTenant(): ?int
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        if ($user['role'] === 'superadmin') {
            $impersonating = $_SESSION['impersonating_tenant'] ?? null;

            return $impersonating === null ? null : (int) $impersonating;
        }

        return $user['created_by'] !== null ? (int) $user['created_by'] : (int) $user['id'];
    }

    /**
     * Codes to actually load this request — the only registry call on the hot
     * path (public/index.php), so it stays one cheap query per tenant and
     * never a disk scan. Modules that threw while loading are excluded, so a
     * broken one can't be retried within the same request.
     *
     * No tenant (logged out, or a SuperUser browsing nobody) means no
     * modules: their routes simply don't exist for that request. A module
     * therefore cannot serve a logged-out visitor — a deliberate limit, since
     * "enabled" is a per-tenant fact and there is no tenant to read it from.
     *
     * @return list<string>
     */
    public static function enabledCodes(?int $ruler = null): array
    {
        $ruler ??= self::currentTenant();
        if ($ruler === null) {
            return [];
        }

        if (!isset(self::$enabledByTenant[$ruler])) {
            self::$enabledByTenant[$ruler] = array_column(
                Db::all('SELECT code FROM module_tenants WHERE ruler = ?', [$ruler]),
                'code'
            );
        }

        return array_values(array_diff(self::$enabledByTenant[$ruler], self::$failed));
    }

    /**
     * Loads every module this tenant has enabled and lets it register its
     * routes and hook listeners.
     *
     * Each one is loaded inside try/catch on purpose: module code is
     * third-party code, and without this a single broken module would fatal
     * every page in the app — including the settings page where it could be
     * switched off, locking the owner out entirely. Instead the failure is
     * recorded, that module is skipped for the rest of the request, and the
     * app carries on without it.
     */
    public static function boot(Router $router): void
    {
        foreach (self::enabledCodes() as $code) {
            try {
                $file = self::dir($code) . '/Module.php';
                if (!is_file($file)) {
                    throw new \RuntimeException("Module.php missing for $code");
                }

                require_once $file;
                $class = "App\\Modules\\$code\\Module";
                if (!class_exists($class) || !is_subclass_of($class, ModuleInterface::class)) {
                    throw new \RuntimeException("$class must implement " . ModuleInterface::class);
                }

                (new $class())->register(new ModuleRouter($router, $code));
            } catch (\Throwable $e) {
                self::fail($code, $e);
            }
        }
    }

    /** @return array<string, string> code => message, for modules that failed to load this request */
    public static function failures(): array
    {
        return self::$failedMessages;
    }

    /** @var array<string, string> */
    private static array $failedMessages = [];

    private static function fail(string $code, \Throwable $e): void
    {
        self::$failed[]              = $code;
        self::$failedMessages[$code] = $e->getMessage();
        error_log("[module:$code] " . $e->getMessage());
    }

    /**
     * discover() + installed() merged into the shape the settings page and
     * the topbar dropdown render. Admin-page cost (discover() hits disk).
     *
     * @return list<array{code:string,name:string,description:?string,version:string,author:?string,installed:bool,enabled:bool,icon:string,installedVersion:?string}>
     */
    public static function summaries(?int $ruler = null): array
    {
        $installed = self::installed();
        $enabled   = self::enabledCodes($ruler);

        $out = [];
        foreach (self::discover() as $code => $manifest) {
            $out[] = [
                'code'             => $code,
                'name'             => self::manifestText($code, $manifest, 'name') ?? $code,
                'description'      => self::manifestText($code, $manifest, 'description'),
                'version'          => (string) ($manifest['version'] ?? ''),
                'installedVersion' => $installed[$code]['version'] ?? null,
                'author'           => isset($manifest['author']) ? (string) $manifest['author'] : null,
                'installed'        => isset($installed[$code]),
                'enabled'          => in_array($code, $enabled, true),
                'icon'             => self::manifestIcon($manifest),
            ];
        }

        usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * A manifest's name/description may be either literal text or one of the
     * module's own translation keys — t() returns the key itself when it has
     * no entry, so a literal string survives translation untouched either way.
     */
    private static function manifestText(string $code, array $manifest, string $field): ?string
    {
        if (!isset($manifest[$field]) || !is_string($manifest[$field])) {
            return null;
        }

        Lang::loadModule($code);

        return t($manifest[$field]);
    }

    private static function manifestIcon(array $manifest): string
    {
        $icon = (string) ($manifest['icon'] ?? '');

        // Bootstrap Icons class names only — a manifest is third-party text
        // and this value lands in a class attribute.
        return preg_match('/^bi-[a-z0-9-]+$/', $icon) ? $icon : 'bi-puzzle';
    }

    /** Runs the module's own migrations and records it as installed (server-wide). */
    public static function install(string $code): void
    {
        $manifest = self::discover()[$code] ?? throw new \RuntimeException("Unknown module: $code");

        Migrator::run(self::dir($code) . '/migrations', "$code/");

        Db::conn()->prepare(
            'INSERT INTO modules (code, version) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version)'
        )->execute([$code, (string) ($manifest['version'] ?? '0.0.0')]);

        self::$installed = null;
    }

    /**
     * Removes the module server-wide: its own uninstall.sql (which drops its
     * tables), its migration ledger rows, every tenant's enable row, and the
     * installed record. The files stay on disk — deleting those is the
     * separate "remove files" step, so an uninstall can be undone by
     * installing again.
     *
     * Callers are expected to have exported the module's data first
     * (ModuleArchive::exportData()) — the settings page does, and the
     * confirmation shown there says so.
     */
    public static function uninstall(string $code): void
    {
        $sql = self::dir($code) . '/uninstall.sql';
        if (is_file($sql)) {
            Db::conn()->exec((string) file_get_contents($sql));
        }

        Db::conn()->prepare('DELETE FROM migrations WHERE name LIKE ?')->execute(["$code/%"]);
        Db::conn()->prepare('DELETE FROM module_tenants WHERE code = ?')->execute([$code]);
        Db::conn()->prepare('DELETE FROM modules WHERE code = ?')->execute([$code]);

        self::$installed       = null;
        self::$enabledByTenant = [];
    }

    public static function enable(string $code, ?int $ruler = null): void
    {
        $ruler ??= Auth::tenantId();
        Db::conn()->prepare(
            'INSERT IGNORE INTO module_tenants (code, ruler) VALUES (?, ?)'
        )->execute([$code, $ruler]);

        unset(self::$enabledByTenant[$ruler]);
    }

    public static function disable(string $code, ?int $ruler = null): void
    {
        $ruler ??= Auth::tenantId();
        Db::conn()->prepare('DELETE FROM module_tenants WHERE code = ? AND ruler = ?')->execute([$code, $ruler]);

        unset(self::$enabledByTenant[$ruler]);
    }

    /**
     * The tables a module owns: every DROP TABLE in its uninstall.sql,
     * lowercased. One declaration serves three things — the pre-uninstall
     * export (ModuleArchive::exportData), the write boundary (ModuleDb) and
     * the uninstall itself — so they cannot disagree about what is the
     * module's. A module without uninstall.sql owns nothing and can write
     * nothing.
     *
     * @return list<string>
     */
    public static function ownedTables(string $code): array
    {
        $file = self::dir($code) . '/uninstall.sql';
        if (!is_file($file)) {
            return [];
        }

        preg_match_all(
            '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i',
            (string) file_get_contents($file),
            $m
        );

        return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
    }

    /** Tenants with this module switched on — the uninstall confirmation shows the count. @return list<int> */
    public static function tenantsUsing(string $code): array
    {
        return array_map('intval', array_column(
            Db::all('SELECT ruler FROM module_tenants WHERE code = ?', [$code]),
            'ruler'
        ));
    }
}
