<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProductType;
use App\Models\Unit;

/**
 * product_types and units are both a bare id+name table, managed from a modal
 * on the products page (see products.php). Same shape, same two actions
 * (add / rename) — one generic handler for both instead of duplicating it.
 *
 * Unlike CustomerController::store(), this responds with JSON and never
 * redirects: the modal must not blow away whatever the surrounding product
 * form already has typed into it.
 */
final class LookupController extends Controller
{
    public function productTypes(): void
    {
        $this->save(ProductType::class, 'ptype.err_name_required');
    }

    public function units(): void
    {
        $this->save(Unit::class, 'unit.err_name_required');
    }

    /** @param class-string $model must expose create(string):int and update(int,string):void */
    private function save(string $model, string $requiredKey): void
    {
        csrf_verify();
        header('Content-Type: application/json; charset=utf-8');

        $id        = trim((string) ($_POST['id'] ?? ''));
        $name      = trim((string) ($_POST['name'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => t($requiredKey)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($name) > 255) {
            http_response_code(422);
            echo json_encode(['error' => t('cust.err_too_long', 255)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($editingId !== null) {
            $model::update($editingId, $name);
        } else {
            $editingId = $model::create($name);
        }

        echo json_encode(['id' => $editingId, 'name' => $name], JSON_UNESCAPED_UNICODE);
    }
}
