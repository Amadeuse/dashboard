<?php

declare(strict_types=1);

namespace App\Core;

/**
 * What every module's Module.php implements. One method: register whatever
 * the module needs — its routes on the given ModuleRouter (which forces them
 * under /m/<code>/), and its Hooks::on(...) listeners into core extension
 * points.
 *
 * Note the parameter is ModuleRouter, not Router: a module never touches the
 * real router, so it cannot register a path outside its own prefix or
 * override a core route. See /help/modules for the full author's guide.
 */
interface ModuleInterface
{
    public function register(ModuleRouter $router): void;
}
