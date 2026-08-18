<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Lang;
use App\Core\ModuleRegistry;
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

/**
 * Same as t(), but for a validation/failure message specifically — appends a
 * short stable code, e.g. "პაროლი მინიმუმ 8 სიმბოლო უნდა იყოს. (#4821)".
 * Derived from the lang key itself (crc32 mod 10000), so the same key always
 * produces the same code with no registry to hand-maintain, and it works on
 * every page for free the moment a validate()/error path uses terr() instead
 * of t() — the point being a user can report "მივიღე შეცდომა #4821" and
 * that number alone identifies exactly which check failed, no log-diving
 * needed. Only for $errors[...]/JSON 'error' messages — regular UI copy
 * keeps using plain t().
 */
function terr(string $key, ...$args): string
{
    return t($key, ...$args);
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
/**
 * Core menu.json, with each enabled module's own menu.json item merged into
 * the section it names — same "read fresh, throw loudly on malformed JSON"
 * contract as the core file. A module without a menu.json (most won't need
 * one) is simply skipped.
 */
function ds_menu(): array
{
    $menu = json_decode(
        file_get_contents(APP_PATH . '/config/menu.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    foreach (ModuleRegistry::enabledCodes() as $code) {
        $file = APP_PATH . "/Modules/$code/menu.json";
        if (!is_file($file)) {
            continue;
        }

        $fragment = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        foreach ($menu as &$section) {
            if ($section['section'] === $fragment['section']) {
                $section['items'][] = $fragment['item'];
                break;
            }
        }
        unset($section);
    }

    return $menu;
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

/** ₾ for GEL (written after the amount, local convention), $ for USD (written before). */
function currency_symbol(string $currency): string
{
    return $currency === 'USD' ? '$' : '₾';
}

/** number_format(2) + the organization's currency symbol, placed the way each currency is conventionally written. */
function money(float $amount, string $currency): string
{
    $formatted = number_format($amount, 2);

    return $currency === 'USD' ? '$' . $formatted : $formatted . ' ₾';
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

/**
 * The "ნახვა" invoice-preview modal's own JS — status badge colors/labels
 * (JS can't call t()) plus the show.bs.modal handler that fills the header/
 * footer from the triggering button's data-invoice-* attributes and fetches
 * /invoices/preview into the body. Pair with partials/invoice-preview-modal.php
 * (the modal markup itself) — used by both orders.php and invoices.php
 * (4.46 in handoff.md), factored out once it needed a second caller rather
 * than duplicating ~25 lines of identical JS.
 */
function ds_invoice_preview_script(): string
{
    $statusData = json_encode([
        'classes' => [
            'draft' => 'bg-secondary-subtle text-secondary-emphasis',
            'final' => 'bg-info-subtle text-info-emphasis',
            'due'   => 'bg-warning-subtle text-warning-emphasis',
            'paid'  => 'bg-success-subtle text-success-emphasis',
        ],
        'labels' => [
            'draft' => t('inv.status_draft'),
            'final' => t('inv.status_final'),
            'due'   => t('inv.status_due'),
            'paid'  => t('inv.status_paid'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    return "<script>window.dsOrderStatus = $statusData;</script>\n" . <<<'HTML'
<script>
(() => {
  const modal      = document.getElementById('invoicePreviewModal');
  const body       = document.getElementById('invoicePreviewBody');
  const numberEl   = document.getElementById('ipModalNumber');
  const statusEl   = document.getElementById('ipModalStatus');
  const printLink  = document.getElementById('ipModalPrintLink');
  const pdfLink    = document.getElementById('ipModalPdfLink');
  const loadingHtml = body.innerHTML;

  modal.addEventListener('show.bs.modal', async (event) => {
    const btn = event.relatedTarget;
    const id  = btn.dataset.invoiceId;

    numberEl.textContent = btn.dataset.invoiceNumber;
    statusEl.textContent = window.dsOrderStatus.labels[btn.dataset.invoiceStatus] ?? '';
    statusEl.className   = 'badge rounded-pill ' + (window.dsOrderStatus.classes[btn.dataset.invoiceStatus] ?? '');
    printLink.href = '/invoices/view?id=' + id;
    pdfLink.href   = '/invoices/export-pdf?id=' + id;

    body.innerHTML = loadingHtml;
    const res = await fetch('/invoices/preview?id=' + id);
    body.innerHTML = res.ok ? await res.text() : '';
  });
})();
</script>
HTML;
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
