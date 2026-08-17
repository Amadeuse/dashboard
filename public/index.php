<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\ModuleRegistry;
use App\Core\Router;

/**
 * Reachable without a session — every other path needs Auth::check().
 * `/invoices/view` is here for one reason only: a vendor sharing a link
 * (?id=N&token=...) with their customer, who has no account here at all.
 * That doesn't make the path itself public — InvoiceController::show()
 * still requires either a matching token or a logged-in, same-tenant
 * viewer; this list only decides whether the *blanket* redirect-to-/login
 * fires before the controller gets a chance to make that real decision.
 */
const PUBLIC_PATHS = [
    '/login', '/login/otp/send', '/login/otp/verify', '/logout',
    '/register', '/auth/google', '/auth/google/callback', '/auth/photo',
    '/forgot-password', '/reset-password', '/invoices/view',
];

// `php -S` has no .htaccess: let it serve existing asset files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../app/bootstrap.php';

// App-wide login gate. Same path-normalising rule as Router (trailing slash
// stripped, empty -> "/") so this can never disagree with what actually gets
// dispatched below. Auth::check() is per-session (one file per session-id
// cookie) — concurrent users on different sessions never see each other's
// login state, see Auth's docblock.
$requestPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
if (!Auth::check() && !in_array($requestPath, PUBLIC_PATHS, true)) {
    redirect('/login');
}

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
