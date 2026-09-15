<?php

declare(strict_types=1);

return [
    'iw.module_name'        => 'Invoice workflow',
    'iw.module_description' => 'Adds payment (unpaid/partial/paid) and cancellation tracking to invoices, separate from the core status.',

    'iw.label'           => 'Payment',
    'iw.payment_unpaid'  => 'Unpaid',
    'iw.payment_partial' => 'Partially paid',
    'iw.payment_paid'    => 'Paid',
    'iw.cancelled_label' => 'Cancelled',
    'iw.cancel'          => 'Cancel invoice',
    'iw.uncancel'        => 'Undo cancellation',
    'iw.save'            => 'Save',
];
