<?php

declare(strict_types=1);

use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\LookupController;
use App\Controllers\ProductController;
use App\Controllers\StyleGuideController;

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
$router->post('/product-types', [LookupController::class, 'productTypes']);
$router->post('/units', [LookupController::class, 'units']);
