<?php

declare(strict_types=1);

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\ModuleRegistry;
use App\Core\Router;

/**
 * Reachable without a session — every other path needs Auth::check().
 * `/invoices/view` and `/invoices/export-pdf` are here for one reason only:
 * a vendor sharing a link (?id=N&token=...) with their customer, who has no
 * account here at all — the PDF-download button on that same view page
 * needs to work for them too. That doesn't make either path itself public —
 * InvoiceController::resolveInvoiceForView() still requires either a
 * matching token or a logged-in, same-tenant viewer; this list only decides
 * whether the *blanket* redirect-to-/login fires before the controller gets
 * a chance to make that real decision.
 */
const PUBLIC_PATHS = [
    '/login', '/login/otp/send', '/login/otp/verify', '/logout',
    '/register', '/auth/google', '/auth/google/callback', '/auth/photo',
    '/forgot-password', '/reset-password', '/invoices/view', '/invoices/export-pdf',
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
$loggedIn    = Auth::check();
if (!$loggedIn && !in_array($requestPath, PUBLIC_PATHS, true)) {
    redirect('/login');
}

// SuperUser's activity log (4.89, /superuser/activity) — one row per
// authenticated request, "who went where and when". Single choke point:
// every page view and form submit a logged-in user makes already passes
// through here, so no per-controller call sites are needed to stay
// complete. $_SESSION['user_id'] directly (not Auth::user(), which would
// re-run the same check() work Auth::check() above just did).
if ($loggedIn) {
    ActivityLog::record((int) $_SESSION['user_id'], $_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? $requestPath);
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
