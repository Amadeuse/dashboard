<?php

declare(strict_types=1);

namespace App\Core;

/**
 * One method: register whatever the module needs — its own routes on
 * $router, and its Hooks::on(...) listeners into core extension points.
 * See app/Modules/Warehouse/Module.php for the reference implementation.
 */
interface ModuleInterface
{
    public function register(Router $router): void;
}
