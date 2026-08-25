<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Modules\InvoiceWorkflow\Models\InvoiceWorkflow;

/**
 * Payment/cancellation tracking, independent of invoices.status — see
 * InvoiceWorkflow model docblock and handoff.md. No GET page of its own:
 * the badge + these forms are rendered inline on orders.php/invoices.php
 * (core), only when this module is enabled.
 */
final class InvoiceWorkflowController extends Controller
{
    public function updatePayment(): void
    {
        $invoiceId = $this->guard();

        if ($invoiceId !== null) {
            [$clean, $errors] = InvoiceWorkflow::validate($_POST);
            if ($errors === []) {
                InvoiceWorkflow::setPayment($invoiceId, $clean['payment_state'], $clean['paid_amount']);
            }
        }

        redirect($this->backTo());
    }

    public function cancel(): void
    {
        $invoiceId = $this->guard();
        if ($invoiceId !== null) {
            InvoiceWorkflow::cancel($invoiceId);
        }

        redirect($this->backTo());
    }

    public function uncancel(): void
    {
        $invoiceId = $this->guard();
        if ($invoiceId !== null) {
            InvoiceWorkflow::uncancel($invoiceId);
        }

        redirect($this->backTo());
    }

    /** Shared csrf/impersonation/ownership check for all three actions — the owned invoice id, or null if rejected. */
    private function guard(): ?int
    {
        csrf_verify();
        Auth::requireNotImpersonating();

        $invoiceId = (string) ($_POST['invoice_id'] ?? '');
        if (!ctype_digit($invoiceId)) {
            return null;
        }

        $memberIds = User::tenantMemberIds(Auth::tenantId());
        if (!InvoiceWorkflow::invoiceOwnedBy((int) $invoiceId, $memberIds)) {
            return null;
        }

        return (int) $invoiceId;
    }

    /** Same open-redirect guard as ModuleController::backTo() — stay on the page the form was submitted from. */
    private function backTo(): string
    {
        $to = (string) ($_POST['redirect'] ?? '');

        return str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/orders';
    }
}
