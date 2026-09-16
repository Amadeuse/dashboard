<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow\Controllers;

use App\Core\Auth;
use App\Core\ModuleDb;
use App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow;

/**
 * Routes registered by Module.php land here — all of them under
 * /m/invoiceworkflow/, enforced by ModuleRouter.
 *
 * The prefix is collision-proofing, not access control, so every action below
 * does its own two checks before writing: csrf_verify(), and that the invoice
 * really belongs to the caller's tenant. A module is trusted code running
 * with full Db access; that makes these checks the module author's job, and
 * skipping them here would be an IDOR in the module rather than in core.
 */
final class InvoiceWorkflowController
{
    public function updatePayment(): void
    {
        $id = $this->ownedInvoiceId();
        csrf_verify();

        InvoiceWorkflow::setPayment(
            $id,
            (string) ($_POST['payment_state'] ?? 'unpaid'),
            (float) ($_POST['paid_amount'] ?? 0),
        );

        $this->back();
    }

    public function cancel(): void
    {
        $id = $this->ownedInvoiceId();
        csrf_verify();
        InvoiceWorkflow::setCancelled($id, true);
        $this->back();
    }

    public function uncancel(): void
    {
        $id = $this->ownedInvoiceId();
        csrf_verify();
        InvoiceWorkflow::setCancelled($id, false);
        $this->back();
    }

    /**
     * The posted invoice id, but only if it was created by someone in the
     * caller's own tenant — otherwise 404, the same answer a non-existent id
     * gets, so this can't be used to probe which ids exist.
     */
    private function ownedInvoiceId(): int
    {
        Auth::requireUser();

        $id  = (int) ($_POST['invoice_id'] ?? 0);
        $ids = Auth::invoiceScopeUserIds();
        if ($id <= 0 || $ids === []) {
            $this->deny();
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // A read of a core table — exactly what ModuleDb::select() is for.
        $found = ModuleDb::for('InvoiceWorkflow')->select(
            "SELECT id FROM invoices WHERE id = ? AND created_by IN ($placeholders)",
            array_merge([$id], $ids)
        );

        if ($found === []) {
            $this->deny();
        }

        return $id;
    }

    private function deny(): never
    {
        http_response_code(404);
        (new \App\Controllers\ErrorController())->notFound();
        exit;
    }

    /** Back where the form was submitted from, same-origin paths only. */
    private function back(): never
    {
        $to = (string) ($_POST['redirect'] ?? '');
        redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/orders');
        exit;
    }
}
