<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ModuleRegistry;

/**
 * Serves app/Modules/<Code>/assets/<file> over HTTP (4.113).
 *
 * Modules live outside the docroot, so their CSS/JS can't be reached by the
 * web server directly — see ds_module_assets(), which builds these URLs.
 *
 * Everything here is written against one assumption: the path is attacker-
 * controlled. The module code must match ModuleRegistry's own pattern, the
 * filename is matched against a strict whitelist pattern with no directory
 * separators at all, the extension must be one of a fixed few, and the
 * resolved realpath() must still sit inside that module's assets directory —
 * so neither "..", a symlink, nor an encoded separator can walk out of it.
 * Content-Type is chosen from our own map, never from the file.
 */
final class ModuleAssetController
{
    private const TYPES = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function show(): void
    {
        $code = (string) ($_GET['code'] ?? '');
        $file = (string) ($_GET['file'] ?? '');

        // No separators, no leading dot, one extension — "a.css", not "../x".
        if (!ModuleRegistry::isValidCode($code)
            || !preg_match('/^[A-Za-z0-9_-]{1,64}\.([A-Za-z0-9]{1,5})$/', $file, $m)
            || !isset(self::TYPES[strtolower($m[1])])) {
            $this->notFound();
            return;
        }

        $dir  = ModuleRegistry::dir($code) . '/assets';
        $real = realpath($dir . '/' . $file);
        $base = realpath($dir);

        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
            $this->notFound();
            return;
        }

        $mtime = (int) filemtime($real);
        header('Content-Type: ' . self::TYPES[strtolower($m[1])]);
        header('Content-Length: ' . filesize($real));
        // Immutable: ds_module_assets() puts the mtime in the query string, so
        // an edited file arrives under a different URL.
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('X-Content-Type-Options: nosniff');

        readfile($real);
    }

    private function notFound(): void
    {
        http_response_code(404);
        (new ErrorController())->notFound();
    }
}
