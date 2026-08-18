<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Customer;
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
