<?php

declare(strict_types=1);

use App\Core\ModuleRegistry;
use App\Core\Router;

// `php -S` has no .htaccess: let it serve existing asset files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../app/bootstrap.php';

$router = new Router();
require APP_PATH . '/routes.php';   // registers core routes on $router

// Enabled modules register their own routes and Hooks::on(...) listeners.
// enabledCodes() is one cheap query — never a disk scan — since this runs
// on every request, not just the modules admin page.
foreach (ModuleRegistry::enabledCodes() as $code) {
    require APP_PATH . "/Modules/$code/Module.php";
    $class = "App\\Modules\\$code\\Module";
    (new $class())->register($router);
}

$router->dispatch($_SERVER['REQUEST_URI'] ?? '/');
