<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow;

use App\Core\Hooks;
use App\Core\Lang;
use App\Core\ModuleInterface;
use App\Core\ModuleRouter;
use App\Modules\InvoiceWorkflow\Controllers\InvoiceWorkflowController;
use App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow;

/**
 * Reference implementation of the module contract (4.113) — the first module
 * written against it, and the one /help/modules walks through.
 *
 * Version 1 of this module was deleted in 4.112 because it did the opposite
 * of everything below: its strings sat in app/lang/*.php and its markup was
 * pasted straight into orders.php and invoices.php, so core could not be read
 * without knowing this module existed. Nothing here touches a core file. Take
 * the folder away and core is unchanged; the three hook points simply have no
 * listener and render nothing.
 */
final class Module implements ModuleInterface
{
    public function register(ModuleRouter $router): void
    {
        // Registered as '/payment' — ModuleRouter makes it
        // /m/invoiceworkflow/payment. A module cannot claim a path outside
        // its own prefix.
        $router->post('/payment',  [InvoiceWorkflowController::class, 'updatePayment']);
        $router->post('/cancel',   [InvoiceWorkflowController::class, 'cancel']);
        $router->post('/uncancel', [InvoiceWorkflowController::class, 'uncancel']);

        Lang::loadModule('InvoiceWorkflow');

        // ---- data: one query for a whole page of invoices ------------------
        Hooks::on('invoice.list.data', static fn(array $ctx): array => [
            'InvoiceWorkflow' => InvoiceWorkflow::forMany($ctx['ids'] ?? []),
        ]);

        // ---- render: a badge in the orders table's status cell -------------
        Hooks::on('render.invoice.row.badges', static function (array $ctx): string {
            $row = $ctx['data']['InvoiceWorkflow'][(int) $ctx['invoice']['id']] ?? null;
            if ($row === null) {
                return '';
            }

            if ($row['cancelled_at'] !== null) {
                return '<span class="badge rounded-pill bg-dark-subtle text-dark-emphasis">'
                     . e(t('iw.cancelled_label')) . '</span>';
            }

            $class = [
                'unpaid'  => 'bg-danger-subtle text-danger-emphasis',
                'partial' => 'bg-warning-subtle text-warning-emphasis',
                'paid'    => 'bg-success-subtle text-success-emphasis',
            ][$row['payment_state']] ?? 'bg-secondary-subtle';

            // Colours are Bootstrap's own semantic utilities, not hex values:
            // the module inherits the app's palette and theme rather than
            // bringing its own (see /help/modules, "Visual rules").
            return '<span class="badge rounded-pill ' . $class . '">'
                 . e(t('iw.payment_' . $row['payment_state'])) . '</span>';
        });

        // ---- render: the payment/cancel panel on the invoice form ----------
        Hooks::on('render.invoice.form.aside', static function (array $ctx): string {
            $invoice = $ctx['invoice'] ?? null;
            if ($invoice === null) {
                return '';   // a new invoice has no id yet — nothing to track
            }

            $id  = (int) $invoice['id'];
            $wf  = InvoiceWorkflow::for($id);
            $to  = '/invoices?edit=' . $id;
            $ret = '<input type="hidden" name="invoice_id" value="' . $id . '">'
                 . '<input type="hidden" name="redirect" value="' . e($to) . '">';

            $options = '';
            foreach (InvoiceWorkflow::STATES as $state) {
                $options .= '<option value="' . $state . '"'
                    . ($wf['payment_state'] === $state ? ' selected' : '') . '>'
                    . e(t('iw.payment_' . $state)) . '</option>';
            }

            $cancelled = $wf['cancelled_at'] !== null;

            return '<hr class="my-1">'
                 . '<form method="post" action="/m/invoiceworkflow/payment" class="d-flex gap-1">'
                 . csrf_field() . $ret
                 . '<select class="form-select form-select-sm" name="payment_state">' . $options . '</select>'
                 . '<input type="number" step="0.01" min="0" class="form-control form-control-sm"'
                 . ' name="paid_amount" value="' . e((string) $wf['paid_amount']) . '"'
                 . ' aria-label="' . e(t('iw.label')) . '">'
                 . '<button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0">'
                 . '<i class="bi bi-check-lg"></i></button>'
                 . '</form>'
                 . '<form method="post" action="/m/invoiceworkflow/' . ($cancelled ? 'uncancel' : 'cancel') . '">'
                 . csrf_field() . $ret
                 . '<button type="submit" class="btn btn-sm btn-outline-'
                 . ($cancelled ? 'secondary' : 'danger') . ' w-100">'
                 . e($cancelled ? t('iw.uncancel') : t('iw.cancel')) . '</button>'
                 . '</form>';
        });
    }
}
