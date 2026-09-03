<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Customer;
use App\Models\CustomerReport;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use PDOException;

final class CustomerController extends Controller
{
    public function index(): void
    {
        $rows = Customer::all(Auth::tenantId());

        $this->view('customers', [
            'title'   => t('page.customers') . ' · ' . app_name(),
            'rows'    => $rows,
            'total'   => count($rows),
            'errors'  => flash('errors') ?? [],
            'old'     => flash('old') ?? [],
            'created' => flash('created'),
            'updated' => flash('updated'),
        ]);
    }

    /**
     * One customer's report — customers.php's "რეპორტი" row-link (4.69).
     * Invoices scoped the same way orders.php's list is (Invoice::all()'s
     * own convention): every tenant member's invoices to this customer,
     * not just the current user's own.
     */
    public function report(): void
    {
        $ruler    = Auth::tenantId();
        $customer = Customer::find((int) ($_GET['id'] ?? 0), $ruler);

        if ($customer === null) {
            http_response_code(404);
            (new ErrorController())->notFound();
            return;
        }

        $memberIds     = User::tenantMemberIds($ruler);
        $org           = Organization::get($ruler);
        $invoicePrefix = (string) ($org['invoice_prefix'] ?? '') ?: 'INV';
        $invoices      = CustomerReport::invoices($customer['id'], $memberIds);

        // Inline "ნახვა" — rendered server-side for the default (most
        // recent, $invoices is already sequence_number DESC) invoice so the
        // page shows one on first load, not an empty panel; clicking a
        // different row in the list re-fetches this same partial via
        // InvoiceController::preview() (unchanged) instead of a modal (4.70
        // — replaces the invoice-preview-modal.php this page used to embed).
        $defaultInvoiceId   = $invoices[0]['id'] ?? null;
        $invoicePreviewHtml = '';
        if ($defaultInvoiceId !== null) {
            $invoicePreviewHtml = $this->renderToString('invoice-preview', [
                'invoice'   => Invoice::find($defaultInvoiceId),
                'items'     => Invoice::itemsFor($defaultInvoiceId),
                'org'       => $org,
                'bankIbans' => Organization::bankIbans($org),
            ]);
        }

        $this->view('customer-report', [
            'title'              => $customer['customer_name'] . ' · ' . t('page.customers') . ' · ' . app_name(),
            'customer'           => $customer,
            'invoicePrefix'      => $invoicePrefix,
            'currency'           => (string) $org['currency'],
            'invoices'           => $invoices,
            'defaultInvoiceId'   => $defaultInvoiceId,
            'invoicePreviewHtml' => $invoicePreviewHtml,
            'summary'            => CustomerReport::summary($customer['id'], $memberIds),
            'statusTotals'       => CustomerReport::statusTotals($customer['id'], $memberIds),
            'monthly'            => CustomerReport::monthlyTotals($customer['id'], $memberIds),
            'topProducts'        => CustomerReport::topProducts($customer['id'], $memberIds),
        ]);
    }

    public function store(): void
    {
        csrf_verify();
        Auth::requireNotImpersonating();

        $ruler = Auth::tenantId();

        // Row-click editing (customers.php) fills this hidden field from the table;
        // its absence is what tells an ordinary submit apart from an edit.
        $id         = trim((string) ($_POST['customer_id'] ?? ''));
        $editingId  = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = Customer::validate($_POST, $ruler, $editingId);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['customer_id' => $id]);
            redirect('/customers#customer-form');
        }

        try {
            if ($editingId !== null) {
                Customer::update($editingId, $clean, $ruler);
                flash('updated', $clean['customer_name']);
            } else {
                Customer::create($clean, $ruler);
                flash('created', $clean['customer_name']);
            }
        } catch (PDOException $e) {
            // Customer::validate() already pre-checks taxIdTaken(), but that's a
            // check-then-insert with a real race window under concurrent
            // requests (see handoff.md's multi-user section) — two submits for
            // the same never-before-used tax id, close enough together, could
            // both pass the pre-check. The actual guarantee is the DB's own
            // UNIQUE index (migrations/025); this just turns that constraint
            // violation into the same friendly message instead of a fatal error.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            flash('errors', ['customer_taxid' => terr('cust.err_taxid_taken')]);
            flash('old', $clean + ['customer_id' => $id]);
            redirect('/customers#customer-form');
        }

        redirect('/customers');
    }
}
