<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Unit;

final class ProductController extends Controller
{
    public function index(): void
    {
        $ruler = Auth::tenantId();
        $rows  = Product::all($ruler);

        $this->view('products', [
            'title'   => t('page.products') . ' · ' . app_name(),
            'rows'    => $rows,
            'units'   => Unit::all(),
            'productTypes' => ProductType::all($ruler),
            'currency' => (string) Organization::get($ruler)['currency'],
            'total'   => count($rows),
            'errors'  => flash('errors') ?? [],
            'old'     => flash('old') ?? [],
            'created' => flash('created'),
            'updated' => flash('updated'),
        ]);
    }

    public function store(): void
    {
        csrf_verify();
        Auth::requireNotImpersonating();

        $ruler = Auth::tenantId();

        $id        = trim((string) ($_POST['product_id'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = Product::validate($_POST, $ruler);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['product_id' => $id]);
            redirect('/products#product-form');
        }

        if ($editingId !== null) {
            Product::update($editingId, $clean, $ruler);
            flash('updated', $clean['name']);
        } else {
            Product::create($clean, $ruler);
            flash('created', $clean['name']);
        }

        redirect('/products');
    }
}
