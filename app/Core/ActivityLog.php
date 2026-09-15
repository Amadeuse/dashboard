<?php

declare(strict_types=1);

namespace App\Core;

/**
 * `activity_log` — one row per authenticated request: who, when, from which
 * IP, which route. Written from a single choke point (public/index.php,
 * right after the app-wide login gate) rather than scattered per-controller
 * calls — every page visit and form submit a logged-in user makes already
 * passes through there, so this needs no per-action instrumentation to stay
 * complete. SuperUser's own /superuser/activity page (4.89 in handoff.md) is
 * the only reader — see describe() for how a raw "METHOD /path" becomes the
 * readable "მოქმედება" column there.
 *
 * ponytail: no pagination yet, all() just caps at $limit newest rows — add
 * real paging if the table outgrows a few hundred rows being "enough".
 */
final class ActivityLog
{
    /**
     * AJAX/JSON helper endpoints, not real navigation — logging these would
     * just be noise (a product-type dropdown re-fetch on every keystroke-
     * adjacent change), not "სად გადავიდა" in any meaningful sense. Matched
     * against the path with its query string already stripped.
     */
    private const SKIP_PATHS = ['/units', '/product-types', '/invoices/preview', '/auth/photo'];

    public static function record(int $userId, string $method, string $path): void
    {
        $base = explode('?', $path, 2)[0];
        if (in_array($base, self::SKIP_PATHS, true)) {
            return;
        }

        Db::conn()->prepare('INSERT INTO activity_log (user_id, method, path, ip_address) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $method, $path, $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    /** @return array<int, array<string,mixed>> newest first, optionally narrowed to one user */
    public static function all(?int $userId, int $limit = 300): array
    {
        if ($userId !== null) {
            return Db::all(
                'SELECT a.*, u.name AS user_name, u.color AS user_color
                   FROM activity_log a JOIN users u ON u.id = a.user_id
                  WHERE a.user_id = ?
                  ORDER BY a.id DESC LIMIT ' . max(1, $limit),
                [$userId]
            );
        }

        return Db::all(
            'SELECT a.*, u.name AS user_name, u.color AS user_color
               FROM activity_log a JOIN users u ON u.id = a.user_id
              ORDER BY a.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * "POST /login" → "შესვლა" — a fixed map for the routes worth naming;
     * anything not listed (a future route, or a query-string variant that
     * doesn't matter for the label) falls back to the raw "METHOD /path",
     * still fully informative, just not prettified.
     */
    private const LABELS = [
        'GET /'                          => 'superuser.act_dashboard',
        'GET /style-guide'               => 'superuser.act_style_guide',
        'GET /customers'                 => 'superuser.act_customers_view',
        'POST /customers'                => 'superuser.act_customers_save',
        'GET /customers/report'          => 'superuser.act_customer_report',
        'GET /products'                  => 'superuser.act_products_view',
        'POST /products'                 => 'superuser.act_products_save',
        'GET /invoices'                  => 'superuser.act_invoices_view',
        'POST /invoices'                 => 'superuser.act_invoices_save',
        'GET /invoices/view'             => 'superuser.act_invoice_view',
        'GET /invoices/export-pdf'       => 'superuser.act_invoice_pdf',
        'POST /invoices/send-email'      => 'superuser.act_invoice_email',
        'GET /orders'                    => 'superuser.act_orders_view',
        'GET /orders/export-pdf'         => 'superuser.act_orders_pdf',
        'GET /settings/modules'          => 'superuser.act_modules_view',
        'POST /settings/modules/install' => 'superuser.act_module_install',
        'POST /settings/modules/enable'  => 'superuser.act_module_enable',
        'POST /settings/modules/disable' => 'superuser.act_module_disable',
        'POST /settings/modules/upload'       => 'superuser.act_module_upload',
        'POST /settings/modules/uninstall'    => 'superuser.act_module_uninstall',
        'POST /settings/modules/remove-files' => 'superuser.act_module_remove_files',
        'GET /help'                          => 'superuser.act_help_view',
        'GET /help/modules'                  => 'superuser.act_help_modules',
        'POST /login'                    => 'superuser.act_login',
        'POST /logout'                   => 'superuser.act_logout',
        'GET /profile'                   => 'superuser.act_profile_view',
        'POST /profile'                  => 'superuser.act_profile_save',
        'GET /profile/settings'          => 'superuser.act_profile_settings_view',
        'POST /profile/settings'         => 'superuser.act_profile_settings_save',
        'GET /settings/users'            => 'superuser.act_users_view',
        'POST /settings/users'           => 'superuser.act_users_save',
        'GET /settings/organization'     => 'superuser.act_org_view',
        'POST /settings/organization'    => 'superuser.act_org_save',
        'GET /superuser'                 => 'superuser.act_superuser_view',
        'GET /superuser/activity'        => 'superuser.act_activity_view',
        'POST /superuser/impersonate'    => 'superuser.act_impersonate',
        'POST /superuser/stop'           => 'superuser.act_stop_impersonate',
        'POST /superuser/toggle-block'   => 'superuser.act_toggle_block',
    ];

    public static function describe(string $method, string $path): string
    {
        $base = explode('?', $path, 2)[0];
        $key  = self::LABELS[$method . ' ' . $base] ?? null;

        return $key !== null ? t($key) . ' — ' . $path : $method . ' ' . $path;
    }
}
