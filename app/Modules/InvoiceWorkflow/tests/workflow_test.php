<?php

declare(strict_types=1);

/**
 * Run with:  php tools/module-kit/module-test.php InvoiceWorkflow
 *
 * The harness has already copied the live schema into a scratch database, run
 * this module's migrations, seeded users 1 (tenant) and 2 (member), signed
 * user 1 in, and called Module::register() — so Hooks listeners are live and
 * everything below runs against the real core.
 */

use App\Core\Db;
use App\Core\Hooks;
use App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow;

/** An invoice to hang workflow rows off — the module's FK requires a real one. */
function iw_make_invoice(int $createdBy = 1): int
{
    $pdo = Db::conn();
    $pdo->prepare('INSERT INTO customers (customer_name, ruler) VALUES (?, ?)')->execute(['Test Customer', 1]);
    $customerId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO invoices (customer_id, issue_date, created_by) VALUES (?, ?, ?)')
        ->execute([$customerId, date('Y-m-d'), $createdBy]);

    return (int) $pdo->lastInsertId();
}

test('an invoice with no row reads as unpaid', function () {
    $id = iw_make_invoice();
    $wf = InvoiceWorkflow::for($id);

    assert_same('unpaid', $wf['payment_state']);
    assert_same(null, $wf['cancelled_at']);
});

test('setPayment stores state and amount', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'partial', 42.50);

    $wf = InvoiceWorkflow::for($id);
    assert_same('partial', $wf['payment_state']);
    assert_same('42.50', $wf['paid_amount']);
});

test('setPayment ignores a state outside the enum', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'nonsense', 10.0);

    // Rejected before it reaches the database, so nothing was written and the
    // default reading still applies.
    assert_same('unpaid', InvoiceWorkflow::for($id)['payment_state']);
    assert_same(0, count(Db::all('SELECT * FROM invoice_workflow WHERE invoice_id = ?', [$id])));
});

test('a negative paid amount is clamped to zero', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'paid', -5.0);

    assert_same('0.00', InvoiceWorkflow::for($id)['paid_amount']);
});

test('cancelling and un-cancelling round-trips', function () {
    $id = iw_make_invoice();

    InvoiceWorkflow::setCancelled($id, true);
    assert_true(InvoiceWorkflow::for($id)['cancelled_at'] !== null);

    InvoiceWorkflow::setCancelled($id, false);
    assert_same(null, InvoiceWorkflow::for($id)['cancelled_at']);
});

test('forMany returns one map keyed by invoice id', function () {
    $a = iw_make_invoice();
    $b = iw_make_invoice();
    InvoiceWorkflow::setPayment($a, 'paid', 10.0);

    $map = InvoiceWorkflow::forMany([$a, $b]);

    assert_same('paid', $map[$a]['payment_state']);
    assert_true(!isset($map[$b]), 'an invoice with no row should be absent, not defaulted');
});

test('forMany on an empty list does not query', function () {
    assert_same([], InvoiceWorkflow::forMany([]));
});

test('the data hook answers with this module\'s own key', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'paid', 99.0);

    $data = Hooks::merge('invoice.list.data', ['ids' => [$id]]);

    assert_true(isset($data['InvoiceWorkflow']), 'the hook result must be namespaced by module code');
    assert_same('paid', $data['InvoiceWorkflow'][$id]['payment_state']);
});

test('the badge hook renders nothing when there is no row', function () {
    $id   = iw_make_invoice();
    $html = Hooks::render('render.invoice.row.badges', [
        'invoice' => ['id' => $id],
        'data'    => ['InvoiceWorkflow' => []],
    ]);

    assert_same('', $html);
});

test('the badge hook uses design-system colours, never a hex value', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'partial', 1.0);

    $data = Hooks::merge('invoice.list.data', ['ids' => [$id]]);
    $html = Hooks::render('render.invoice.row.badges', ['invoice' => ['id' => $id], 'data' => $data]);

    assert_true(str_contains($html, 'bg-warning-subtle'), 'expected a Bootstrap semantic class');
    assert_true(!preg_match('/#[0-9a-f]{3,6}\b/i', $html), 'a hard-coded colour would break dark mode');
});

test('the form hook renders nothing for an unsaved invoice', function () {
    assert_same('', Hooks::render('render.invoice.form.aside', ['invoice' => null]));
});

test('the form hook posts to this module\'s own prefixed route', function () {
    $id   = iw_make_invoice();
    $html = Hooks::render('render.invoice.form.aside', ['invoice' => ['id' => $id]]);

    assert_true(str_contains($html, '/m/invoiceworkflow/payment'), 'routes must stay under the module prefix');
    assert_true(str_contains($html, '_token'), 'every POST form needs a CSRF field');
});

test('deleting an invoice takes its workflow row with it', function () {
    $id = iw_make_invoice();
    InvoiceWorkflow::setPayment($id, 'paid', 5.0);

    Db::conn()->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);

    // ON DELETE CASCADE in the module's own migration — a module must not
    // leave orphans behind in its tables when core rows go.
    assert_same(0, count(Db::all('SELECT * FROM invoice_workflow WHERE invoice_id = ?', [$id])));
});
