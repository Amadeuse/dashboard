<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Unit;

final class ProductController extends Controller
{
    public function index(): void
    {
        $rows = Product::all();

        $this->view('products', [
            'title'   => t('page.products') . ' · ' . app_name(),
            'rows'    => $rows,
            'units'   => Unit::all(),
            'productTypes' => ProductType::all(),
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

        $id        = trim((string) ($_POST['product_id'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = Product::validate($_POST);

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['product_id' => $id]);
            redirect('/products#product-form');
        }

        if ($editingId !== null) {
            Product::update($editingId, $clean);
            flash('updated', $clean['name']);
        } else {
            Product::create($clean);
            flash('created', $clean['name']);
        }

        redirect('/products');
    }
}
