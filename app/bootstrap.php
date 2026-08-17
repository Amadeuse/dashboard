<?php

declare(strict_types=1);

define('APP_PATH', __DIR__);
define('ROOT_PATH', dirname(__DIR__));

// Composer packages (currently just mpdf/mpdf, for PDF export — see
// App\Core\Pdf). Everything else in this app is dependency-free by design
// (vendor/ under public/ is this project's own hand-copied JS/CSS, not
// Composer), so this is the one place a real package's autoloader is needed.
require ROOT_PATH . '/vendor/autoload.php';

// PSR-4-ish autoloader: App\Core\Router -> app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/Core/helpers.php';

// .env lives outside the docroot, next to app/ — load it before anything reads config.
App\Core\Env::load(ROOT_PATH . '/.env');

// Never show PHP errors to visitors in production.
$debug = (bool) env('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');

// Both set cookies — must run before any output.
// The session backs the CSRF token and one-request flash messages.
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
App\Core\Lang::boot();
