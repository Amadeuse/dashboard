<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Unit;

/**
 * units is a bare id+name table, managed from a modal on the products page
 * (see products.php). product_type_id's own equivalent lookup — same shape,
 * same two actions (add / rename) — is App\Modules\Warehouse\Controllers\
 * ProductTypeController, which reuses the identical ~15-line save() body
 * rather than sharing a base class across the core/module boundary for it.
 *
 * Unlike CustomerController::store(), this responds with JSON and never
 * redirects: the modal must not blow away whatever the surrounding product
 * form already has typed into it.
 */
final class LookupController extends Controller
{
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
            echo json_encode(['error' => terr($requiredKey)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($name) > 255) {
            http_response_code(422);
            echo json_encode(['error' => terr('cust.err_too_long', 255)], JSON_UNESCAPED_UNICODE);
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
