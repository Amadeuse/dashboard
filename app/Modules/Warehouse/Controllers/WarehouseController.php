<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Db;
use App\Models\Product;
use App\Modules\Warehouse\Models\ProductType;
use App\Modules\Warehouse\Models\ProductWarehouse;

final class WarehouseController extends Controller
{
    private const UPLOAD_DIR = ROOT_PATH . '/public/assets/uploads/products/';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const ALLOWED_EXT = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];

    public function index(): void
    {
        $rows = ProductWarehouse::all();

        $this->viewAt(__DIR__ . '/../Views/warehouse.php', [
            'title'        => t('page.warehouse') . ' · ' . app_name(),
            'rows'         => $rows,
            'products'     => Product::all(Auth::tenantId()),
            'productTypes' => ProductType::all(),
            'total'        => count($rows),
            'errors'       => flash('errors') ?? [],
            'old'          => flash('old') ?? [],
            'saved'        => flash('saved'),
        ]);
    }

    public function store(): void
    {
        csrf_verify();
        Auth::requireNotImpersonating();

        [$clean, $errors] = ProductWarehouse::validate($_POST);

        $image = $this->resolveImage($errors);
        $clean['image'] = $image ?? (string) ($_POST['existing_image'] ?? '');

        if ($errors) {
            flash('errors', $errors);
            flash('old', $clean + ['existing_image' => $clean['image']]);
            redirect('/warehouse#warehouse-form');
        }

        ProductWarehouse::upsert((int) $clean['product_id'], $clean);

        $name = Db::all('SELECT name FROM products WHERE id = ?', [$clean['product_id']])[0]['name']
            ?? $clean['product_id'];
        flash('saved', $name);

        redirect('/warehouse');
    }

    /**
     * Same shape as Customer/Product's own upload handling: validates first
     * (errors appended by reference), only touches the filesystem once the
     * request is otherwise known-good enough to proceed.
     */
    private function resolveImage(array &$errors): ?string
    {
        $file = $_FILES['image'] ?? null;
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = terr('prod.err_image');
            return null;
        }

        if ($file['size'] > self::MAX_IMAGE_BYTES) {
            $errors['image'] = terr('prod.err_image_size');
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_EXT[$ext])) {
            $errors['image'] = terr('prod.err_image_type');
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
