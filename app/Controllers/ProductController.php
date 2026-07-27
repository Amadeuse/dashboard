<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Unit;

final class ProductController extends Controller
{
    private const UPLOAD_DIR = ROOT_PATH . '/public/assets/uploads/products/';
    private const UPLOAD_URL = '/assets/uploads/products/';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];

    public function index(): void
    {
        $rows = Product::all();

        $this->view('products', [
            'title'        => t('page.products') . ' · ' . app_name(),
            'rows'         => $rows,
            'productTypes' => ProductType::all(),
            'units'        => Unit::all(),
            'total'        => count($rows),
            'errors'       => flash('errors') ?? [],
            'old'          => flash('old') ?? [],
            'created'      => flash('created'),
            'updated'      => flash('updated'),
        ]);
    }

    public function store(): void
    {
        csrf_verify();

        $id        = trim((string) ($_POST['product_id'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        [$clean, $errors] = Product::validate($_POST);

        $image = $this->resolveImage($errors);
        $clean['image'] = $image ?? (string) ($_POST['existing_image'] ?? '');

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['product_id' => $id, 'existing_image' => $clean['image']]);
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

    /**
     * Validates the uploaded file (if any) and moves it into place, deleting
     * whatever image the same request is replacing. Returns null when no new
     * file was sent (keep the existing one) — errors are appended by reference.
     */
    private function resolveImage(array &$errors): ?string
    {
        $file = $_FILES['image'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = t('prod.err_image');
            return null;
        }

        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            $errors['image'] = t('prod.err_image_size');
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXT[$ext])) {
            $errors['image'] = t('prod.err_image_type');
            return null;
        }

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], self::UPLOAD_DIR . $filename);

        $old = (string) ($_POST['existing_image'] ?? '');
        if ($old !== '' && is_file(self::UPLOAD_DIR . $old)) {
            unlink(self::UPLOAD_DIR . $old);
        }

        return $filename;
    }
}
