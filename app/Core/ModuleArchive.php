<?php

declare(strict_types=1);

namespace App\Core;

use ZipArchive;

/**
 * Installing a module from a .zip, and exporting its data before it is
 * removed (4.113).
 *
 * Extracting an uploaded archive of PHP that the app will then `require` is
 * the most dangerous thing in this codebase, so nothing here trusts the
 * archive. Every entry is inspected *before* a single byte is written:
 *
 *   - the zip must contain exactly one top-level directory, and that name
 *     becomes the module code — it must pass ModuleRegistry::isValidCode(),
 *     so it can never be "..", a path, or anything but letters and digits;
 *   - every entry path is rejected outright if it is absolute, contains a
 *     "..' segment, a backslash, or a NUL — this is the zip-slip class of
 *     attack, where an entry named "../../public/index.php" escapes the
 *     extraction directory and overwrites application code;
 *   - every file's extension must be on a whitelist, so an archive cannot
 *     drop a .htaccess, a .phar, or a shell script into the tree;
 *   - module.json must parse and carry a version;
 *   - the whole thing is extracted to a temporary directory first and only
 *     moved into app/Modules/ once all of that has passed.
 *
 * What this does NOT do is make an untrusted module safe to run. Its PHP
 * executes with the same access core has (see /help/modules). These checks
 * stop a malicious *archive* from writing outside its own folder; they do not
 * stop malicious *code* inside a module you chose to install.
 */
final class ModuleArchive
{
    private const MAX_BYTES   = 20_971_520;   // 20 MB
    private const MAX_ENTRIES = 2_000;

    private const ALLOWED_EXT = [
        'php', 'json', 'sql', 'css', 'js', 'md', 'txt',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'woff', 'woff2',
    ];

    /**
     * Validates and installs the uploaded archive, returning the module code.
     *
     * @param  array{tmp_name?:string,size?:int,error?:int} $upload one entry of $_FILES
     * @throws \RuntimeException with a translated message, for the caller to flash
     */
    public static function installUpload(array $upload): string
    {
        $tmp = (string) ($upload['tmp_name'] ?? '');
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            throw new \RuntimeException(t('modules.err_upload'));
        }
        if ((int) ($upload['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException(t('modules.err_too_big'));
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new \RuntimeException(t('modules.err_not_zip'));
        }

        try {
            $code = self::inspect($zip);
            $dest = ModuleRegistry::dir($code);

            if (is_dir($dest)) {
                throw new \RuntimeException(t('modules.err_exists', $code));
            }

            $staging = self::staging();
            if (!$zip->extractTo($staging)) {
                self::rmdir($staging);
                throw new \RuntimeException(t('modules.err_extract'));
            }

            self::requireManifest($staging . '/' . $code);

            if (!@rename($staging . '/' . $code, $dest)) {
                self::rmdir($staging);
                throw new \RuntimeException(t('modules.err_extract'));
            }
            self::rmdir($staging);

            return $code;
        } finally {
            $zip->close();
        }
    }

    /**
     * Reads every entry name and decides whether this archive is safe to
     * extract at all. Returns the module code (the single top-level folder).
     */
    private static function inspect(ZipArchive $zip): string
    {
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new \RuntimeException(t('modules.err_zip_shape'));
        }

        $roots = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            // Zip-slip and friends. Checked on the raw entry name, before any
            // normalising, so nothing can be smuggled through a rewrite.
            if ($name === ''
                || str_contains($name, '\\')
                || str_contains($name, "\0")
                || str_starts_with($name, '/')
                || preg_match('#(^|/)\.\.(/|$)#', $name)
                || preg_match('#^[A-Za-z]:#', $name)) {
                throw new \RuntimeException(t('modules.err_unsafe_path', $name));
            }

            $parts   = explode('/', trim($name, '/'));
            $roots[] = $parts[0];

            // Directory entry — nothing more to check.
            if (str_ends_with($name, '/')) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                throw new \RuntimeException(t('modules.err_bad_file', $name));
            }
        }

        $roots = array_values(array_unique($roots));
        if (count($roots) !== 1 || !ModuleRegistry::isValidCode($roots[0])) {
            throw new \RuntimeException(t('modules.err_zip_shape'));
        }

        return $roots[0];
    }

    /** The manifest has to exist, parse, and name a version — checked before the folder is accepted. */
    private static function requireManifest(string $dir): void
    {
        $file = $dir . '/module.json';
        if (!is_file($file)) {
            throw new \RuntimeException(t('modules.err_no_manifest'));
        }

        $manifest = json_decode((string) file_get_contents($file), true);
        if (!is_array($manifest) || !isset($manifest['version'])) {
            throw new \RuntimeException(t('modules.err_no_manifest'));
        }

        if (!is_file($dir . '/Module.php')) {
            throw new \RuntimeException(t('modules.err_no_entry'));
        }
    }

    /**
     * Dumps every table the module owns, as CREATE + INSERTs, to
     * storage/backups/. Called before uninstall so removing a module is
     * recoverable — the user's own requirement: reinstalling and replaying
     * this file brings the data back.
     *
     * Which tables it owns is read from its uninstall.sql (the DROP TABLE
     * statements) — the module already has to declare them there, so there is
     * nothing extra for an author to keep in sync.
     *
     * @return string|null path of the file written, or null if it owns no tables
     */
    public static function exportData(string $code): ?string
    {
        $sqlFile = ModuleRegistry::dir($code) . '/uninstall.sql';
        if (!is_file($sqlFile)) {
            return null;
        }

        preg_match_all(
            '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i',
            (string) file_get_contents($sqlFile),
            $m
        );
        $tables = array_unique($m[1] ?? []);
        if ($tables === []) {
            return null;
        }

        $pdo = Db::conn();
        $out = "-- $code data, exported " . date('Y-m-d H:i:s') . " before uninstall.\n"
             . "-- Reinstall the module, then replay this file to restore it.\n\n";
        $rowCount = 0;

        foreach ($tables as $table) {
            if (Db::all('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])[0]['c'] == 0) {
                continue;
            }

            $out .= Db::all("SHOW CREATE TABLE `$table`")[0]['Create Table'] . ";\n\n";
            foreach (Db::all("SELECT * FROM `$table`") as $row) {
                $vals = array_map(
                    static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($row)
                );
                $out .= "INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . '`) VALUES ('
                      . implode(',', $vals) . ");\n";
                $rowCount++;
            }
            $out .= "\n";
        }

        $dir = ROOT_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/' . $code . '_' . date('Y-m-d_His') . '.sql';
        file_put_contents($path, $out);

        return $path;
    }

    /** Deletes a module's files — the separate step after uninstall(). */
    public static function removeFiles(string $code): void
    {
        if (ModuleRegistry::isValidCode($code)) {
            self::rmdir(ModuleRegistry::dir($code));
        }
    }

    private static function staging(): string
    {
        $dir = ROOT_PATH . '/storage/tmp/module_' . bin2hex(random_bytes(8));
        mkdir($dir, 0775, true);

        return $dir;
    }

    private static function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
