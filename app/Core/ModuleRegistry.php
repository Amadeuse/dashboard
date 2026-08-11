<?php

declare(strict_types=1);

namespace App\Core;

/**
 * `modules` table (migrations/005_create_modules.sql) is the single source of
 * truth for install/enable state. A module's own on-disk `module.json` is
 * only metadata about what's *available* — discover() reads that, and is
 * deliberately kept off the hot path (see enabledCodes() below).
 */
final class ModuleRegistry
{
    private static ?array $enabledCodes = null;
    private static ?array $installed    = null;

    /**
     * Every app/Modules/*\/module.json on disk, keyed by module code (the
     * folder name). Filesystem + JSON cost — the admin page only, never the
     * per-request route-loading loop (see enabledCodes()).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function discover(): array
    {
        $modules = [];
        foreach (glob(APP_PATH . '/Modules/*/module.json') as $file) {
            $code = basename(dirname($file));
            $modules[$code] = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
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

    public static function isEnabled(string $code): bool
    {
        return (bool) (self::installed()[$code]['enabled'] ?? false);
    }

    /**
     * discover() + installed() merged into the shape both the /settings/modules
     * page and the topbar apps dropdown render: code/name/description/version/
     * installed/enabled/icon. Admin-page cost (discover() hits disk) — not for
     * the per-request hot path, see enabledCodes().
     *
     * @return list<array{code:string,name:string,description:?string,version:string,installed:bool,enabled:bool,icon:string}>
     */
    public static function summaries(): array
    {
        $installed = self::installed();

        $out = [];
        foreach (self::discover() as $code => $manifest) {
            $out[] = [
                'code'        => $code,
                'name'        => isset($manifest['name']) ? t($manifest['name']) : $code,
                'description' => isset($manifest['description']) ? t($manifest['description']) : null,
                'version'     => $installed[$code]['version'] ?? $manifest['version'] ?? '',
                'installed'   => isset($installed[$code]),
                'enabled'     => (bool) ($installed[$code]['enabled'] ?? false),
                'icon'        => self::menuIcon($code) ?? 'bi-puzzle',
            ];
        }

        return $out;
    }

    /** The icon a module's own menu.json item uses, if it has one — same file ds_menu() merges. */
    private static function menuIcon(string $code): ?string
    {
        $file = APP_PATH . "/Modules/$code/menu.json";
        if (!is_file($file)) {
            return null;
        }

        $fragment = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $fragment['item']['icon'] ?? null;
    }

    /**
     * Codes to actually load this request — the only registry call on the
     * hot path (public/index.php, every request), so it must stay a single
     * cheap query, never a disk scan.
     *
     * @return list<string>
     */
    public static function enabledCodes(): array
    {
        if (self::$enabledCodes === null) {
            self::$enabledCodes = array_column(
                Db::all('SELECT code FROM modules WHERE enabled = 1'),
                'code'
            );
        }

        return self::$enabledCodes;
    }

    /** Runs the module's own migrations and records it as installed. */
    public static function install(string $code): void
    {
        $manifest = self::discover()[$code] ?? throw new \RuntimeException("Unknown module: $code");

        Migrator::run(APP_PATH . "/Modules/$code/migrations", "$code/");

        Db::conn()->prepare(
            'INSERT INTO modules (code, version, enabled) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE version = VALUES(version)'
        )->execute([$code, (string) ($manifest['version'] ?? '0.0.0'), (int) ($manifest['enabled_by_default'] ?? true)]);

        self::$installed = null;
    }

    public static function enable(string $code): void
    {
        self::setEnabled($code, true);
    }

    public static function disable(string $code): void
    {
        self::setEnabled($code, false);
    }

    private static function setEnabled(string $code, bool $enabled): void
    {
        Db::conn()->prepare('UPDATE modules SET enabled = ? WHERE code = ?')->execute([(int) $enabled, $code]);
        self::$installed = null;
    }
}
