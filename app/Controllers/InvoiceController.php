<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $org = Organization::get();

        $errors = flash('errors') ?? [];
        $old    = flash('old') ?? [];
        $editingInvoice = null;

        // A fresh GET with ?edit=N (from /orders' pencil icon, or the conflict
        // redirect below) loads that invoice into $old exactly like a
        // failed-validation resubmit would — the whole form already knows how
        // to redraw itself from $old, so this needed no new rendering logic,
        // just a different way to fill it. Only the *old* guard matters here:
        // a failed resubmit's own flashed $old always wins over a fresh load,
        // but its $errors (if any) must NOT block this branch — the conflict
        // redirect below flashes an error and relies on this re-fetching the
        // now-current row in the same response.
        if ($old === [] && ctype_digit((string) ($_GET['edit'] ?? ''))) {
            $editId  = (int) $_GET['edit'];
            $invoice = Invoice::find($editId);

            if ($invoice !== null) {
                $editingInvoice = $invoice;
                $items = Invoice::itemsFor($editId);
                $old = [
                    'invoice_id'      => (string) $editId,
                    'customer_id'     => (string) $invoice['customer_id'],
                    'status'          => (string) $invoice['status'],
                    'is_zero'         => $invoice['is_zero'] ? 1 : 0,
                    'is_recurring'    => $invoice['is_recurring'] ? 1 : 0,
                    'notes'           => (string) ($invoice['notes'] ?? ''),
                    'updated_at'      => (string) $invoice['updated_at'],
                    'item_product_id' => array_column($items, 'product_id'),
                    'item_quantity'   => array_column($items, 'quantity'),
                    'item_unit_price' => array_column($items, 'unit_price'),
                ];
            }
        }

        $invoicePrefix = (string) ($org['invoice_prefix'] ?? '') ?: 'INV';

        // Grouped by customer, for the "this customer's other invoices" panel —
        // computed here (not in the view) since it needs the same numbering
        // rule ($invoicePrefix) the view already applies to every other number.
        $invoicesByCustomer = [];
        foreach (Invoice::all() as $inv) {
            $invoicesByCustomer[(int) $inv['customer_id']][] = [
                'number' => Invoice::number($inv, $invoicePrefix),
                'total'  => number_format((float) $inv['total'], 2),
            ];
        }

        $this->view('invoices', [
            'title'         => t('page.invoices') . ' · ' . app_name(),
            'customers'     => Customer::all(),
            'products'      => Product::all(),
            'org'           => $org,
            'invoicePrefix' => $invoicePrefix,
            'invoicesByCustomer' => $invoicesByCustomer,
            'editingInvoice' => $editingInvoice,
            'errors'        => $errors,
            'old'           => $old,
            'created'       => flash('created'),
            'updated'       => flash('updated'),
        ]);
    }

    /**
     * The invoice list, browsed from the sidebar's შეკვეთები > ყველა შეკვეთა
     * — see orders.php. Scoped to the logged-in user's own invoices (each
     * user sees only what they created), not every invoice in the system.
     */
    public function orders(): void
    {
        $user = Auth::user();
        $rows = Invoice::all($user['id'] ?? null);
        $org  = Organization::get();

        $this->view('orders', [
            'title'         => t('nav.orders_all') . ' · ' . app_name(),
            'rows'          => $rows,
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'total'         => count($rows),
        ]);
    }

    public function store(): void
    {
        csrf_verify();

        $id        = trim((string) ($_POST['invoice_id'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;
        // Only meaningful for an edit — a hidden field the form was rendered
        // with (see invoices.php), Invoice::save() uses it as the optimistic-
        // locking check against the row's real updated_at.
        $expectedUpdatedAt = $editingId !== null ? (string) ($_POST['updated_at'] ?? '') : null;

        [$clean, $errors] = Invoice::validate($_POST);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['invoice_id' => $id, 'updated_at' => $expectedUpdatedAt]);
            redirect('/invoices#invoice-form');
        }

        $currentUser = Auth::user();
        $invoiceId   = Invoice::save($clean, $editingId, $expectedUpdatedAt, $currentUser['id'] ?? null);

        if ($invoiceId === null) {
            // Someone else saved this invoice after the form was loaded (or
            // after the last conflict) — nothing was written. Redirecting to
            // ?edit=N re-enters index()'s fresh-load branch (no 'old' flashed
            // here, so that guard is open) and pulls the row's current state,
            // so the form shows what's actually in the DB now, not what this
            // submit tried to overwrite it with.
            flash('errors', ['conflict' => terr('inv.err_conflict')]);
            redirect('/invoices?edit=' . $editingId . '#invoice-form');
        }

        // Flashed as the already-formatted number (not the raw id) — the success
        // message needs issue_date too, which only exists once the row is saved.
        $org    = Organization::get();
        $number = Invoice::number(Invoice::find($invoiceId), (string) ($org['invoice_prefix'] ?? '') ?: 'INV');
        flash($editingId !== null ? 'updated' : 'created', $number);

        redirect('/invoices');
    }

    /** Printable single-invoice document — logo/org header, bill-to, line items, bank details for payment. */
    public function show(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $invoice = Invoice::find($id);

        if ($invoice === null) {
            // Router::dispatch() sets 404 itself for a genuinely unmatched path,
            // but this route DOES match — the missing thing is the id, so it's
            // on us to set the status before delegating to the same error view.
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        $org    = Organization::get();
        $number = Invoice::number($invoice, (string) ($org['invoice_prefix'] ?? '') ?: 'INV');

        $this->view('invoice-view', [
            'title'         => $number . ' · ' . app_name(),
            'invoice'       => $invoice,
            'invoiceNumber' => $number,
            'items'         => Invoice::itemsFor($id),
            'org'           => $org,
            'bankIbans'     => Organization::bankIbans($org),
        ]);
    }
}
