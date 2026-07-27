<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Customer;

final class CustomerController extends Controller
{
    public function index(): void
    {
        $rows = Customer::all();

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

        // Row-click editing (customers.php) fills this hidden field from the table;
        // its absence is what tells an ordinary submit apart from an edit.
        $id         = trim((string) ($_POST['customer_id'] ?? ''));
        $editingId  = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = Customer::validate($_POST, $editingId);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['customer_id' => $id]);
            redirect('/customers#customer-form');
        }

        if ($editingId !== null) {
            Customer::update($editingId, $clean);
            flash('updated', $clean['customer_name']);
        } else {
            Customer::create($clean);
            flash('created', $clean['customer_name']);
        }

        redirect('/customers');
    }
}
