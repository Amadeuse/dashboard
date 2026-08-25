<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow;

use App\Core\ModuleInterface;
use App\Core\Router;
use App\Modules\InvoiceWorkflow\Controllers\InvoiceWorkflowController;

/**
 * Adds payment/cancellation tracking to invoices, independent of the core
 * invoices.status field — see InvoiceWorkflow model + handoff.md. No page of
 * its own: unlike Warehouse, its data belongs right where invoices are
 * already listed/edited (orders.php, invoices.php), so it's rendered inline
 * there (guarded by ModuleRegistry::enabledCodes()) instead of on a separate
 * screen nobody would look at.
 */
final class Module implements ModuleInterface
{
    public function register(Router $router): void
    {
        $router->post('/invoice-workflow/payment', [InvoiceWorkflowController::class, 'updatePayment']);
        $router->post('/invoice-workflow/cancel', [InvoiceWorkflowController::class, 'cancel']);
        $router->post('/invoice-workflow/uncancel', [InvoiceWorkflowController::class, 'uncancel']);
    }
}
