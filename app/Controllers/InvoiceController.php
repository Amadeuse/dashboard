<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Pdf;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $ruler = Auth::tenantId();
        $org   = Organization::get($ruler);

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
            'customers'     => Customer::all($ruler),
            'products'      => Product::all($ruler),
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
     * — see orders.php. Scoped to the whole tenant (the admin + every
     * sub-user they created), same "the team together" scope the dashboard
     * uses — not just the currently logged-in user's own invoices (was that
     * narrower scope until 4.36: a sub-user's invoices were invisible here
     * to their admin, even though the dashboard already counted them).
     */
    public function orders(): void
    {
        $ruler = Auth::tenantId();
        $rows  = Invoice::all(User::tenantMemberIds($ruler));
        $org   = Organization::get($ruler);

        $this->view('orders', [
            'title'         => t('nav.orders_all') . ' · ' . app_name(),
            'rows'          => $rows,
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'currency'      => (string) $org['currency'],
            'total'         => count($rows),
        ]);
    }

    /** "ექსპორტი PDF" on /orders — same tenant scope as orders() above, via mpdf/mpdf (App\Core\Pdf). */
    public function exportOrdersPdf(): void
    {
        $ruler = Auth::tenantId();
        $rows  = Invoice::all(User::tenantMemberIds($ruler));
        $org   = Organization::get($ruler);

        $html = $this->renderToString('pdf/orders', [
            'rows'          => $rows,
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'org'           => $org,
            'generatedAt'   => ds_date(date('Y-m-d')),
        ]);

        Pdf::download($html, 'orders-' . date('Y-m-d') . '.pdf');
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
        $org    = Organization::get(Auth::tenantId());
        $number = Invoice::number(Invoice::find($invoiceId), (string) ($org['invoice_prefix'] ?? '') ?: 'INV');
        flash($editingId !== null ? 'updated' : 'created', $number);

        redirect('/invoices');
    }

    /**
     * Printable single-invoice document — logo/org header, bill-to, line items,
     * bank details for payment. Two ways in, checked in this order:
     *   1. `?token=` matches the invoice's own view_token — public/index.php's
     *      gate exempts this path exactly so this works with no session at
     *      all, for a vendor to hand this URL straight to their customer.
     *   2. No (or a wrong) token — falls back to the normal login gate, and
     *      the logged-in viewer must belong to the same tenant that issued
     *      the invoice. Anyone else gets 404, not 403 — doesn't confirm the
     *      id even exists to someone probing it.
     */
    public function show(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $token   = (string) ($_GET['token'] ?? '');
        $invoice = Invoice::find($id);

        if ($invoice === null) {
            // Router::dispatch() sets 404 itself for a genuinely unmatched path,
            // but this route DOES match — the missing thing is the id, so it's
            // on us to set the status before delegating to the same error view.
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        $sharedLinkValid = $token !== '' && hash_equals((string) $invoice['view_token'], $token);
        $ownerTenant     = $this->ownerTenant($invoice);
        $viewerTenant    = null;

        if (!$sharedLinkValid) {
            Auth::requireUser();
            $viewerTenant = Auth::tenantId();
            // $ownerTenant === null (no resolvable creator — a legacy invoice
            // predating created_by, or a deleted creator) can never equal a
            // real tenant id, so it's denied here too, not waved through.
            if ($ownerTenant !== $viewerTenant) {
                http_response_code(404);
                (new ErrorController())->notFound();
                return;
            }
        }

        // Falling back to the viewer's own tenant (an orphaned invoice with no
        // resolvable owner, viewed by a logged-in tenant match) is purely
        // cosmetic — whose org branding the print page shows, the access
        // check above already ran. Never calls Auth::tenantId() on the
        // token/no-session path — that would redirect an anonymous, valid-
        // token viewer straight to /login, defeating the point of the token.
        $org    = Organization::get($ownerTenant ?? $viewerTenant ?? 0);
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

    /**
     * "ექსპორტი PDF" — the per-row action on /orders. Same access rule as
     * show()'s no-token branch (login required, viewer must belong to the
     * invoice's own tenant) — this is only ever reached from a button behind
     * the login gate, so there's no equivalent to show()'s anonymous
     * share-link path here.
     */
    public function exportInvoicePdf(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $invoice = Invoice::find($id);

        if ($invoice === null) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        Auth::requireUser();
        if ($this->ownerTenant($invoice) !== Auth::tenantId()) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        $org    = Organization::get(Auth::tenantId());
        $number = Invoice::number($invoice, (string) ($org['invoice_prefix'] ?? '') ?: 'INV');

        $html = $this->renderToString('pdf/invoice', [
            'invoice'       => $invoice,
            'invoiceNumber' => $number,
            'items'         => Invoice::itemsFor($id),
            'org'           => $org,
            'bankIbans'     => Organization::bankIbans($org),
        ]);

        // "PH 2026-08-15 0011" -> "PH-2026-08-15-0011.pdf" — spaces are legal
        // in a Content-Disposition filename, but not worth risking across
        // browsers/OSes when a hyphen reads exactly the same.
        Pdf::download($html, str_replace(' ', '-', $number) . '.pdf', $this->pdfFooterHtml());
    }

    /**
     * Two lines: a red payment-reminder notice above the separator, then
     * "Generated by {APP_NAME}" (linking to APP_URL) below it — a real
     * running mPDF page footer (Pdf::download()'s $footerHtml), not part of
     * the document body, so it stays anchored to every page's bottom margin
     * rather than flowing wherever the body content happens to end. mPDF
     * renders footer HTML in its own context (no shared <style> with the
     * body), hence the inline styles instead of a CSS class.
     */
    private function pdfFooterHtml(): string
    {
        $appUrl = (string) env('APP_URL', '');
        if ($appUrl !== '' && !preg_match('#^https?://#i', $appUrl)) {
            $appUrl = 'https://' . $appUrl;
        }

        $link = $appUrl !== ''
            ? '<a href="' . e($appUrl) . '" style="color:#2563eb;text-decoration:none;">' . e(app_name()) . '</a>'
            : e(app_name());

        return '<div style="font-family: notosansgeorgian, sans-serif; text-align:center;">'
            . '<div style="font-size:8px; color:#dc2626;">' . e(t('inv.pdf_debt_notice')) . '</div>'
            . '<div style="font-size:9px; color:#8a94a6; border-top:1px solid #ddd; padding-top:6px; margin-top:4px;">'
            . t('inv.pdf_generated_by', $link) . '</div>'
            . '</div>';
    }

    /**
     * The tenant that issued this invoice, resolved from its creator
     * (invoices.created_by) the same way Auth::tenantId() resolves the
     * current viewer's — a sub-user's tenant is their creating admin's, not
     * their own id. Null (no fallback here — see show()'s two call sites,
     * which fall back differently depending on why they need this) for a
     * legacy invoice with no created_by (predates migrations/021) or a
     * deleted creator.
     */
    private function ownerTenant(array $invoice): ?int
    {
        $creator = $invoice['created_by'] !== null ? User::findById((int) $invoice['created_by']) : null;

        return $creator !== null ? (int) ($creator['created_by'] ?? $creator['id']) : null;
    }
}
