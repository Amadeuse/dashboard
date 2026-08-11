<?php

declare(strict_types=1);

namespace App\Modules\Warehouse;

use App\Core\ModuleInterface;
use App\Core\Router;
use App\Modules\Warehouse\Controllers\ProductTypeController;
use App\Modules\Warehouse\Controllers\WarehouseController;

/**
 * Its own page (/warehouse) — stock/type/image tracking for existing
 * products, separate from the Products page itself. No Hooks::* here: this
 * module doesn't extend core views, so it doesn't need to.
 */
final class Module implements ModuleInterface
{
    public function register(Router $router): void
    {
        $router->get('/warehouse', [WarehouseController::class, 'index']);
        $router->post('/warehouse', [WarehouseController::class, 'store']);
        $router->post('/product-types', [ProductTypeController::class, 'save']);
    }
}
