<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Static checks a module must pass — the enforceable half of the module
 * concept (4.119). Run by the test harness before a module's tests, and by
 * ModuleArchive before an uploaded ZIP is accepted, so a module that breaks
 * these rules neither passes its tests nor gets installed.
 *
 * Rule 1 — style: a module's CSS may not touch core's classes. Every selector
 *          in assets/module.css must start with the module's own prefix
 *          (`.<code lowercase>-…`), so nothing it writes can restyle a .btn,
 *          a .card, or another module.
 * Rule 3 — data: a module may not use App\Core\Db. All access goes through
 *          ModuleDb, which lets it read anywhere and write only to its own
 *          tables. This check is what makes that more than a suggestion.
 * Rule 0 — the one above all: a module changes no core file. Nothing under
 *          the module folder may reference app/lang, app/Views or
 *          app/Controllers paths.
 *
 * Deliberately plain string/regex checks over the module's own files — not a
 * PHP parser. They catch the honest mistake and the lazy shortcut; they do
 * not pretend to catch a determined author, who is bound by review, not code.
 */
final class ModuleLint
{
    /**
     * @return list<string> problems, empty when the module passes
     */
    public static function check(string $code): array
    {
        return self::checkDir(ModuleRegistry::dir($code), $code);
    }

    /** Same checks over an arbitrary folder — ModuleArchive lints the staged upload before moving it in. @return list<string> */
    public static function checkDir(string $dir, string $code): array
    {
        $dir      = rtrim(str_replace('\\', '/', $dir), '/');
        $problems = [];

        foreach (self::phpFiles($dir) as $file) {
            $rel = substr($file, strlen($dir) + 1);
            // Code only: a docblock that *explains* the Db rule must not trip it.
            $src = self::stripComments((string) file_get_contents($file));

            // tests/ may touch Db to set up fixtures; module code may not.
            if (!str_starts_with($rel, 'tests/')
                && preg_match('/\bApp\\\\Core\\\\Db\b|(?<![A-Za-z\\\\])Db::(conn|all)\b/', $src)) {
                $problems[] = "$rel: uses App\\Core\\Db — a module reads and writes through ModuleDb::for('$code') only (rule 3)";
            }

            if (preg_match('#(app|APP_PATH\s*\.\s*[\'"])/(lang|Views|Controllers|Models|Core)/#', $src)) {
                $problems[] = "$rel: references a core path — a module must not read or write core files (rule 0)";
            }
        }

        $css = $dir . '/assets/module.css';
        if (is_file($css)) {
            $prefix = '.' . strtolower($code) . '-';
            foreach (self::selectors((string) file_get_contents($css)) as $selector) {
                if (!self::selectorIsScoped($selector, $prefix)) {
                    $problems[] = "assets/module.css: selector \"$selector\" is not scoped — every selector must start with $prefix (rule 1)";
                }
            }
        }

        return $problems;
    }

    /**
     * PHP source with comments removed, via the tokenizer — so a rule quoted in
     * a docblock ("never use App\Core\Db") is not mistaken for a violation.
     * Strings are kept: SQL and paths inside them are exactly what the checks
     * are looking for.
     */
    private static function stripComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function phpFiles(string $dir): array
    {
        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $out[] = str_replace('\\', '/', $f->getPathname());
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Every selector in the stylesheet, comma-split, comments and @-blocks'
     * own headers removed. Good enough for hand-written module CSS; a
     * preprocessor's output is not the target.
     *
     * @return list<string>
     */
    private static function selectors(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        // Drop @media/@supports/@keyframes headers but keep their bodies.
        $css = preg_replace('/@(media|supports|container)[^{]*\{/', '{', $css) ?? $css;
        $css = preg_replace('/@keyframes[^{]*\{.*?\}\s*\}/s', '', $css) ?? $css;
        $css = preg_replace('/@[a-z-]+[^;{]*;/', '', $css) ?? $css;

        preg_match_all('/([^{}]+)\{/', $css, $m);

        $out = [];
        foreach ($m[1] as $group) {
            foreach (explode(',', $group) as $sel) {
                $sel = trim($sel);
                if ($sel !== '' && $sel !== '{') {
                    $out[] = $sel;
                }
            }
        }

        return $out;
    }

    /**
     * A selector is scoped when the class it starts with carries the module's
     * prefix — `.iw-panel`, `.iw-panel .badge` (a core class *inside* the
     * module's own element is fine: that styles the module's copy, not core's).
     * Anything starting with a bare element, a core class, an id, `*` or
     * `:root` is not.
     */
    private static function selectorIsScoped(string $selector, string $prefix): bool
    {
        return str_starts_with($selector, $prefix);
    }
}
