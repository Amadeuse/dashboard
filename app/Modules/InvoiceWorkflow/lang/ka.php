<?php

declare(strict_types=1);

/**
 * This module's own strings. Core never sees them until Lang::loadModule()
 * merges them in, and they leave with the module — the whole reason the first
 * version of this module was deleted was that its text lived in app/lang/.
 * Core keys win on collision, so prefix yours ('iw.') to stay clear.
 */
return [
    'iw.module_name'        => 'ინვოისის სტატუსის გაფართოება',
    'iw.module_description' => 'ინვოისებს ამატებს გადახდის (გადაუხდელი/ნაწილობრივ/სრულად) და გაუქმების ცალკე თვალთვალს.',

    'iw.label'           => 'გადახდა',
    'iw.payment_unpaid'  => 'გადაუხდელი',
    'iw.payment_partial' => 'ნაწილობრივ გადახდილი',
    'iw.payment_paid'    => 'სრულად გადახდილი',
    'iw.cancelled_label' => 'გაუქმებული',
    'iw.cancel'          => 'ინვოისის გაუქმება',
    'iw.uncancel'        => 'გაუქმების მოხსნა',
    'iw.save'            => 'შენახვა',
];
