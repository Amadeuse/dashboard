<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\InvoiceController;
use App\Controllers\LookupController;
use App\Controllers\ModuleController;
use App\Controllers\OrganizationController;
use App\Controllers\ProductController;
use App\Controllers\ProfileController;
use App\Controllers\StyleGuideController;
use App\Controllers\SuperUserController;
use App\Controllers\UserController;

/**
 * All routes live here.
 *
 * Same path, two verbs:
 *   $router->get('/settings',  [SettingsController::class, 'edit']);
 *   $router->post('/settings', [SettingsController::class, 'save']);
 *
 * Other verbs:  $router->add('DELETE', '/orders', [...]);
 *
 * Unknown path        -> 404 (ErrorController::notFound)
 * Known path, no verb -> 405 + Allow header (ErrorController::notAllowed)
 *
 * @var App\Core\Router $router
 */

$router->get('/', [DashboardController::class, 'index']);
$router->get('/style-guide', [StyleGuideController::class, 'index']);

$router->get('/customers', [CustomerController::class, 'index']);
$router->post('/customers', [CustomerController::class, 'store']);

$router->get('/products', [ProductController::class, 'index']);
$router->post('/products', [ProductController::class, 'store']);
$router->post('/units', [LookupController::class, 'units']);
$router->post('/product-types', [LookupController::class, 'productTypes']);

$router->get('/invoices', [InvoiceController::class, 'index']);
$router->post('/invoices', [InvoiceController::class, 'store']);
$router->get('/invoices/view', [InvoiceController::class, 'show']);
$router->get('/invoices/export-pdf', [InvoiceController::class, 'exportInvoicePdf']);
$router->get('/orders', [InvoiceController::class, 'orders']);
$router->get('/orders/export-pdf', [InvoiceController::class, 'exportOrdersPdf']);

$router->get('/settings/modules', [ModuleController::class, 'index']);
$router->post('/settings/modules/install', [ModuleController::class, 'install']);
$router->post('/settings/modules/enable', [ModuleController::class, 'enable']);
$router->post('/settings/modules/disable', [ModuleController::class, 'disable']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/login/otp/send', [AuthController::class, 'sendOtp']);
$router->post('/login/otp/verify', [AuthController::class, 'verifyOtp']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/auth/photo', [AuthController::class, 'authPhoto']);

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

$router->get('/auth/google', [AuthController::class, 'googleRedirect']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

$router->get('/forgot-password', [AuthController::class, 'showForgot']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);

$router->get('/reset-password', [AuthController::class, 'showReset']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

$router->get('/profile', [ProfileController::class, 'show']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->get('/profile/settings', [ProfileController::class, 'settings']);
$router->post('/profile/settings', [ProfileController::class, 'updatePassword']);

$router->get('/settings/users', [UserController::class, 'index']);
$router->post('/settings/users', [UserController::class, 'store']);

$router->get('/settings/organization', [OrganizationController::class, 'show']);
$router->post('/settings/organization', [OrganizationController::class, 'save']);

$router->get('/superuser', [SuperUserController::class, 'index']);
$router->post('/superuser/impersonate', [SuperUserController::class, 'impersonate']);
$router->post('/superuser/stop', [SuperUserController::class, 'stop']);
