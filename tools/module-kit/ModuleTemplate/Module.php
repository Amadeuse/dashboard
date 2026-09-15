<?php

declare(strict_types=1);

namespace App\Modules\ModuleTemplate;

use App\Core\Hooks;
use App\Core\Lang;
use App\Core\ModuleInterface;
use App\Core\ModuleRouter;
use App\Modules\ModuleTemplate\Controllers\TemplateController;
use App\Modules\ModuleTemplate\Models\TemplateItem;

/**
 * The module's entry point — the one file the system requires by name.
 *
 * register() is called once per request, only for tenants who have this module
 * enabled, and it runs inside try/catch: if it throws, this module is skipped
 * and the rest of the app carries on. Do registration here and nothing else —
 * no queries, no output. It runs on every single request, including ones that
 * have nothing to do with this module.
 *
 * Everything below is a working example. Delete what you don't need; the only
 * genuinely required part is the class itself.
 */
final class Module implements ModuleInterface
{
    public function register(ModuleRouter $router): void
    {
        // ---- Routes --------------------------------------------------------
        // Written relative to the module. ModuleRouter turns '/list' into
        // '/m/moduletemplate/list' — you cannot register a path outside that
        // prefix, which is what stops two modules colliding.
        $router->get('/list',    [TemplateController::class, 'index']);
        $router->post('/save',   [TemplateController::class, 'save']);
        $router->post('/delete', [TemplateController::class, 'delete']);

        // ---- Translations --------------------------------------------------
        // Merges lang/<current>.php into the string table. Without this, every
        // t('tpl.*') call renders the raw key. Core keys win on collision, so
        // prefix yours ('tpl.') and they can never clash.
        Lang::loadModule('ModuleTemplate');

        // ---- Hooks ---------------------------------------------------------
        // Three kinds. Register only the ones you need — an unused listener is
        // still called on every matching request.

        // data: core hands you every id on the page at once. Return a map
        // keyed by YOUR module code. One query for the page, not one per row.
        Hooks::on('invoice.list.data', static function (array $ctx): array {
            // $ctx['ids'] is a list<int> of the invoices being listed.
            // Example: return ['ModuleTemplate' => Something::forMany($ctx['ids'])];
            return ['ModuleTemplate' => []];
        });

        // render: return an HTML string, or '' for nothing. Whatever you return
        // is echoed into a core view, so escape everything with e().
        Hooks::on('render.invoice.row.badges', static function (array $ctx): string {
            $mine = $ctx['data']['ModuleTemplate'][(int) $ctx['invoice']['id']] ?? null;
            if ($mine === null) {
                return '';
            }

            // Bootstrap's semantic utilities, never a hex colour — that is what
            // makes the badge follow the app's theme in light and dark mode.
            return '<span class="badge rounded-pill bg-info-subtle text-info-emphasis">'
                 . e(t('tpl.badge')) . '</span>';
        });

        // event: a notification. Return value is ignored; do side effects here.
        Hooks::on('invoice.saved', static function (array $ctx): void {
            // $ctx['id'] is the invoice id, $ctx['isNew'] whether it was just
            // created. The row is already written when this fires.
            unset($ctx);
        });

        // Silences "unused import" for the example above — remove with it.
        class_exists(TemplateItem::class);
    }
}
