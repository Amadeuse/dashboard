<?php

declare(strict_types=1);

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
require APP_PATH . '/routes.php';   // registers routes on $router

$router->dispatch($_SERVER['REQUEST_URI'] ?? '/');
