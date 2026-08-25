<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\ModuleRegistry;
use App\Core\Pdf;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $ruler = Auth::tenantId();
        $org   = Organization::get($ruler);

        $errors      = flash('errors') ?? [];
        $old         = flash('old') ?? [];
        // "მეილზე გაგზავნა" modal's own errors/old, kept separate from the
        // main form's — a failed email send/validation shouldn't touch the
        // invoice form's own error state (see sendEmail(), invoices.php).
        $emailErrors = flash('email_errors') ?? [];
        $emailOld    = flash('email_old') ?? [];
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
                    'document_state'  => (string) $invoice['document_state'],
                    'is_zero'         => $invoice['is_zero'] ? 1 : 0,
                    'is_recurring'    => $invoice['is_recurring'] ? 1 : 0,
                    'notes'           => (string) ($invoice['notes'] ?? ''),
                    'updated_at'      => (string) $invoice['updated_at'],
                    'item_product_id' => array_column($items, 'product_id'),
                    'item_unit_id'    => array_column($items, 'unit_id'),
                    'item_quantity'   => array_column($items, 'quantity'),
                    'item_unit_price' => array_column($items, 'unit_price'),
                ];
            }
        }

        $invoicePrefix = (string) ($org['invoice_prefix'] ?? '') ?: 'INV';

        // What the next invoice's number would be right now — the card-header
        // shows this instead of a generic "new" placeholder, both on a fresh
        // load and (via data-new-label, invoices.php's own JS) after
        // "გასუფთავება" resets an edit back to blank. Invoice::number() only
        // reads sequence_number/issue_date off the row, so a synthetic one
        // works exactly like a real invoice would.
        $previewNumber = Invoice::number(
            ['sequence_number' => Invoice::previewNextSequenceNumber(User::tenantMemberIds($ruler), (int) $org['invoice_start_number']), 'issue_date' => date('Y-m-d')],
            $invoicePrefix,
        );

        // InvoiceWorkflow (payment/cancellation tracking) is optional and
        // fully independent of core status — see handoff.md. null means
        // "don't show its UI" (module off, or nothing to edit yet); the
        // module's own class is never `use`-imported here, only referenced
        // by FQCN behind this guard, same spirit as ds_menu()'s module
        // awareness in helpers.php.
        $workflow = null;
        if ($editingInvoice !== null
            && in_array('InvoiceWorkflow', ModuleRegistry::enabledCodes(), true)
            && class_exists(\App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow::class)) {
            $workflow = \App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow::for((int) $editingInvoice['id']);
        }

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
            'units'         => Unit::all(),
            'org'           => $org,
            'invoicePrefix' => $invoicePrefix,
            'previewNumber' => $previewNumber,
            'invoicesByCustomer' => $invoicesByCustomer,
            'editingInvoice' => $editingInvoice,
            'workflow'      => $workflow,
            'errors'        => $errors,
            'old'           => $old,
            'emailErrors'   => $emailErrors,
            'emailOld'      => $emailOld,
            'created'       => flash('created'),
            'updated'       => flash('updated'),
            'emailSent'     => flash('email_sent'),
            'emailFailed'   => flash('email_failed'),
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

        // See index()'s own $workflow block for why this is guarded like this.
        $workflow = [];
        if (in_array('InvoiceWorkflow', ModuleRegistry::enabledCodes(), true)
            && class_exists(\App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow::class)) {
            $workflow = \App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow::forMany(array_column($rows, 'id'));
        }

        $this->view('orders', [
            'title'         => t('nav.orders_all') . ' · ' . app_name(),
            'rows'          => $rows,
            'workflow'      => $workflow,
            'org'           => $org,
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'currency'      => (string) $org['currency'],
            'total'         => count($rows),
            // "მეილზე გაგზავნა" modal state — same flash keys sendEmail()
            // already uses for /invoices (4.55), reused here since only one
            // of the two pages is ever the redirect target of a given submit.
            'emailErrors'   => flash('email_errors') ?? [],
            'emailOld'      => flash('email_old') ?? [],
            'emailSent'     => flash('email_sent'),
            'emailFailed'   => flash('email_failed'),
        ]);
    }

    /**
     * "ექსპორტი PDF" on /orders — same tenant scope as orders() above, via
     * mpdf/mpdf (App\Core\Pdf). ?sign= is the same "ხელმოწერით"/"ხელმოწერის
     * გარეშე" dropdown choice exportInvoicePdf() reads (4.63) — orders.php's
     * button links straight here with the query string, no form/JS needed.
     */
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
            'signed'        => ($_GET['sign'] ?? '1') !== '0',
        ]);

        Pdf::download($html, 'orders-' . date('Y-m-d') . '.pdf');
    }

    public function store(): void
    {
        csrf_verify();
        Auth::requireNotImpersonating();

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

        $ruler       = Auth::tenantId();
        $org         = Organization::get($ruler);
        $currentUser = Auth::user();
        $invoiceId   = Invoice::save(
            $clean,
            $editingId,
            $expectedUpdatedAt,
            $currentUser['id'] ?? null,
            User::tenantMemberIds($ruler),
            (int) $org['invoice_start_number'],
        );

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
        $number = Invoice::number(Invoice::find($invoiceId), (string) ($org['invoice_prefix'] ?? '') ?: 'INV');
        flash($editingId !== null ? 'updated' : 'created', $number);

        // The sidebar's "PDF ექსპორტი"/"გადახედვა" buttons are this same
        // submit button, just with an extra name=value (form="invoiceMainForm",
        // see invoices.php) — one click saves/updates exactly as above, then
        // lands somewhere other than the list. The flash set above still
        // shows next time the tenant visits /invoices, it's just not what
        // this particular response renders. Two submit_action values (not one
        // + a separate "sign" field) — a dropdown of two plain submit buttons
        // needs no JS to pick between them (4.63 in handoff.md).
        if (in_array($_POST['submit_action'] ?? '', ['export_pdf_signed', 'export_pdf_unsigned'], true)) {
            $sign = ($_POST['submit_action'] === 'export_pdf_signed') ? '1' : '0';
            redirect('/invoices/export-pdf?id=' . $invoiceId . '&sign=' . $sign);
        }

        // "გადახედვა" needs the preview *modal*, a client-side thing — a
        // straight redirect can't open it by itself. Reusing the existing
        // ?edit=N reload (index()'s fresh-load branch already knows how to
        // fill the form from a real, saved invoice) plus one query flag the
        // page's own JS checks on load to auto-click the now-populated
        // "გადახედვა" button — same modal, same trigger, as if the tenant
        // had clicked it themselves right after the page reloaded.
        if (($_POST['submit_action'] ?? '') === 'preview') {
            redirect('/invoices?edit=' . $invoiceId . '&preview=1');
        }

        // "მეილზე გაგზავნა" — same trick as "გადახედვა" above: the email
        // modal is client-side, an unsaved invoice needs a real id first.
        if (($_POST['submit_action'] ?? '') === 'email') {
            redirect('/invoices?edit=' . $invoiceId . '&email=1');
        }

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
        $id    = (int) ($_GET['id'] ?? 0);
        $token = (string) ($_GET['token'] ?? '');
        $ctx   = $this->resolveInvoiceForView($id, $token);
        if ($ctx === null) {
            return;
        }

        $this->view('invoice-view', [
            'title'         => $ctx['number'] . ' · ' . app_name(),
            'invoice'       => $ctx['invoice'],
            'invoiceNumber' => $ctx['number'],
            'items'         => Invoice::itemsFor($id),
            'org'           => $ctx['org'],
            'bankIbans'     => Organization::bankIbans($ctx['org']),
            // "PDF შენახვა" (invoice-view.php) links straight to
            // exportInvoicePdf() with this token — that route accepts the
            // same share-link bypass this page does, so an anonymous
            // customer viewing via a shared link can still download the
            // real PDF, not just window.print(). Always the invoice's own
            // token, never the $_GET one — a logged-in tenant viewer got
            // here without a token in the URL at all.
            'viewToken'     => (string) $ctx['invoice']['view_token'],
        ]);
    }

    /**
     * Shared by show() and exportInvoicePdf() — the "view this invoice"
     * access rule: a valid view_token (share-link, no login needed) or a
     * logged-in viewer from the invoice's own tenant. Sends 404 and returns
     * null itself on denial, same convention as loadOwnedInvoiceForPdf().
     */
    private function resolveInvoiceForView(int $id, string $token): ?array
    {
        $invoice = Invoice::find($id);

        if ($invoice === null) {
            // Router::dispatch() sets 404 itself for a genuinely unmatched path,
            // but this route DOES match — the missing thing is the id, so it's
            // on us to set the status before delegating to the same error view.
            http_response_code(404);
            (new ErrorController())->notFound();
            return null;
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
                return null;
            }
        }

        // Falling back to the viewer's own tenant (an orphaned invoice with no
        // resolvable owner, viewed by a logged-in tenant match) is purely
        // cosmetic — whose org branding the page/PDF shows, the access check
        // above already ran. Never calls Auth::tenantId() on the token/no-
        // session path — that would redirect an anonymous, valid-token
        // viewer straight to /login, defeating the point of the token.
        $org    = Organization::get($ownerTenant ?? $viewerTenant ?? 0);
        $number = Invoice::number($invoice, (string) ($org['invoice_prefix'] ?? '') ?: 'INV');

        return ['invoice' => $invoice, 'number' => $number, 'org' => $org];
    }

    /**
     * "ექსპორტი PDF" — the per-row action on /orders (no token, login
     * required, same as before) *and* "PDF შენახვა" on /invoices/view
     * (invoice-view.php always passes the invoice's own view_token) — same
     * dual access rule as show(), via resolveInvoiceForView(), so a customer
     * viewing an invoice through a shared link can download the real PDF
     * too, not just window.print().
     */
    public function exportInvoicePdf(): void
    {
        $id    = (int) ($_GET['id'] ?? 0);
        $token = (string) ($_GET['token'] ?? '');
        $ctx   = $this->resolveInvoiceForView($id, $token);
        if ($ctx === null) {
            return;
        }

        // "ხელმოწერით"/"ხელმოწერის გარეშე" — invoices.php's export dropdown
        // (store()) and orders.php's/invoice-view.php's plain links both land
        // here; absent ?sign= (every link that isn't the dropdown) keeps the
        // pre-4.63 default of always showing it, same as before this existed.
        $signed = ($_GET['sign'] ?? '1') !== '0';

        $html = $this->renderToString('pdf/invoice', [
            'invoice'       => $ctx['invoice'],
            'invoiceNumber' => $ctx['number'],
            'items'         => Invoice::itemsFor($id),
            'org'           => $ctx['org'],
            'bankIbans'     => Organization::bankIbans($ctx['org']),
            'signed'        => $signed,
        ]);

        // "PH 2026-08-15 0011" -> "PH-2026-08-15-0011.pdf" — spaces are legal
        // in a Content-Disposition filename, but not worth risking across
        // browsers/OSes when a hyphen reads exactly the same.
        Pdf::download($html, str_replace(' ', '-', $ctx['number']) . '.pdf', $this->pdfFooterHtml());
    }

    /**
     * "მეილზე გაგზავნა" — invoices.php's own modal (4.55), and orders.php's
     * same modal per-row (4.62) — always behind login (loadOwnedInvoiceForPdf(),
     * no anonymous/token path — unlike show()/exportInvoicePdf(), sending mail
     * is never something a share-link viewer does). Attaches the same PDF
     * exportInvoicePdf() would download. "From" stays this app's own verified
     * MAIL_FROM (a single shared SMTP relay serves every tenant — most
     * providers reject/flag a From they didn't authenticate as); the
     * organization's own email goes in Reply-To instead, so a reply from the
     * customer reaches them.
     */
    public function sendEmail(): void
    {
        csrf_verify();
        Auth::requireNotImpersonating();

        $id  = (int) ($_POST['invoice_id'] ?? 0);
        $ctx = $this->loadOwnedInvoiceForPdf($id);
        if ($ctx === null) {
            return;
        }

        // Which page's modal this came from — whitelisted, not an open
        // redirect (the form only ever sends its own hardcoded hidden value).
        // Absent/anything else keeps the original /invoices behaviour.
        $fromOrders = ($_POST['redirect'] ?? '') === '/orders';

        $to      = trim((string) ($_POST['to'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        $errors = [];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors['to'] = terr('inv.err_email_to');
        }
        if ($message === '') {
            $errors['message'] = terr('inv.err_email_message');
        }

        if ($errors) {
            flash('email_errors', $errors);
            flash('email_old', ['to' => $to, 'message' => $message]);
            redirect($fromOrders ? '/orders?email_error=' . $id : '/invoices?edit=' . $id . '&email=1#invoice-form');
        }

        $html = $this->renderToString('pdf/invoice', [
            'invoice'       => $ctx['invoice'],
            'invoiceNumber' => $ctx['number'],
            'items'         => Invoice::itemsFor($id),
            'org'           => $ctx['org'],
            'bankIbans'     => Organization::bankIbans($ctx['org']),
            // Emailed copy always carries the signature — the with/without
            // choice (4.63) is only for the explicit "ექსპორტი PDF" download.
            'signed'        => true,
        ]);
        $pdfBytes = Pdf::render($html, $this->pdfFooterHtml());

        $body = nl2br(e($message)) . '<br><br>' . $this->orgSignatureHtml($ctx['org']);
        $sent = Mailer::send(
            $to,
            t('inv.email_subject', $ctx['number']),
            $body,
            [['filename' => str_replace(' ', '-', $ctx['number']) . '.pdf', 'content' => $pdfBytes, 'mimeType' => 'application/pdf']],
            (string) ($ctx['org']['email'] ?? '') !== '' ? (string) $ctx['org']['email'] : null,
        );

        if ($sent) {
            // Sent to a customer means it's no longer a draft — see
            // Invoice::markFinal()'s own docblock. Only on an actual
            // delivery; a failed send changes nothing.
            Invoice::markFinal($id);
        }

        flash($sent ? 'email_sent' : 'email_failed', $ctx['number']);
        redirect($fromOrders ? '/orders' : '/invoices?edit=' . $id . '#invoice-form');
    }

    /** Plain-HTML signature block (name/phone/email/address) appended below the sender's own typed message — sendEmail()'s own use, not shared with pdfFooterHtml() (a different document, different rules). */
    private function orgSignatureHtml(array $org): string
    {
        $lines = array_filter([
            (string) ($org['name'] ?? ''),
            (string) ($org['phone'] ?? ''),
            (string) ($org['email'] ?? ''),
            (string) ($org['address'] ?? ''),
        ], static fn(string $v): bool => $v !== '');

        return '<div style="color:#555;font-size:13px;border-top:1px solid #ddd;padding-top:8px;">'
            . implode('<br>', array_map('e', $lines))
            . '</div>';
    }

    /**
     * "ნახვა" — the per-row action on /orders. Fetched (not navigated to)
     * by orders.php's JS and injected into its preview modal's body — same
     * access rule as exportInvoicePdf(), but its own view (invoice-preview.php),
     * a deliberately separate, independently-designed template from
     * pdf/invoice.php per the user's own reference screenshot, not a reuse
     * of the PDF one (an earlier version did reuse it; the user asked for
     * genuinely separate code instead).
     */
    public function preview(): void
    {
        $id  = (int) ($_GET['id'] ?? 0);
        $ctx = $this->loadOwnedInvoiceForPdf($id);
        if ($ctx === null) {
            return;
        }

        echo $this->renderToString('invoice-preview', [
            'invoice'   => $ctx['invoice'],
            'items'     => Invoice::itemsFor($id),
            'org'       => $ctx['org'],
            'bankIbans' => Organization::bankIbans($ctx['org']),
        ]);
    }

    /**
     * @return array{invoice: array, number: string, org: array}|null null
     *   once a 404 has already been sent (missing id, or a real invoice
     *   belonging to a different tenant) — the caller just returns.
     */
    private function loadOwnedInvoiceForPdf(int $id): ?array
    {
        $invoice = Invoice::find($id);

        if ($invoice === null) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return null;
        }

        Auth::requireUser();
        if ($this->ownerTenant($invoice) !== Auth::tenantId()) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return null;
        }

        $org    = Organization::get(Auth::tenantId());
        $number = Invoice::number($invoice, (string) ($org['invoice_prefix'] ?? '') ?: 'INV');

        return ['invoice' => $invoice, 'number' => $number, 'org' => $org];
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
