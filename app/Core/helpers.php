<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Lang;
use App\Core\Router;

/** Read a value from .env, or $default when it is not set. */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

/** Application name / version, both configurable in .env. */
function app_name(): string
{
    return (string) env('APP_NAME', 'Nova');
}

function app_version(): string
{
    return (string) env('APP_VERSION', '1.0');
}

/** Translate. Extra args feed vsprintf — see 'dash.greeting'. */
function t(string $key, ...$args): string
{
    return Lang::get($key, $args);
}

function ds_lang(): string
{
    return Lang::current();
}

function ds_date(string $iso): string
{
    return Lang::date($iso);
}

/** Current path with a different ?lang= — used by the topbar switcher. */
function ds_lang_url(string $code): string
{
    return e(Router::current()) . '?lang=' . $code;
}

/**
 * Sidebar menu, edited in app/config/menu.json — no PHP change needed to add,
 * remove or reorder items. Shape:
 *   [{ "section": "lang.key", "items": [
 *        { "label": "lang.key", "icon": "bi-…", "url": "/path" },
 *        { "label": "lang.key", "icon": "bi-…", "children": [{ "label": …, "url": … }] }
 *   ]}]
 * Read on every request (so edits show on refresh); broken JSON throws instead of
 * silently rendering an empty nav.
 */
function ds_menu(): array
{
    return json_decode(
        file_get_contents(APP_PATH . '/config/menu.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
}

/** Is this the route currently being rendered? */
function ds_is_current(string $path): bool
{
    return Router::current() === $path;
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Everything a page needs to turn its tables into ds-tables: the translated
 * labels plus the script itself. Assign it to $scripts in the view.
 * The CSS ships with the layout, so there is nothing else to include.
 */
function ds_table_script(): string
{
    $labels = json_encode([
        'search'  => t('table.search'),
        'perPage' => t('table.per_page'),
        'showing' => t('table.showing'),
        'empty'   => t('table.empty'),
        'prev'    => t('table.prev'),
        'next'    => t('table.next'),
        'pages'   => t('table.pages'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    return "<script>window.dsTableLabels = $labels;</script>\n"
         . '<script src="/vendor/table/js/ds-table.js"></script>';
}

/* ---- CSRF: every POST form needs csrf_field(), every POST handler csrf_verify() ---- */

/** Hidden input carrying the per-session token. */
function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));

    return '<input type="hidden" name="_token" value="' . e($_SESSION['csrf']) . '">';
}

/** Wrong or missing token → 419, request never reaches the model. */
function csrf_verify(): void
{
    $sent = (string) ($_POST['_token'] ?? '');
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit(t('error.csrf'));
    }
}

/* ---- Flash: survives exactly one redirect (the POST → GET hop) ---- */

function flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $out = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $out;
}

/** POST → redirect → GET, so a refresh never re-submits the form. */
function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}
