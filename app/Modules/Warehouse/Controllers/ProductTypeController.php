<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\Warehouse\Models\ProductType;

/**
 * product_type_id's lookup modal — was App\Controllers\LookupController::
 * productTypes() before Warehouse moved out of core. Same ~15-line body as
 * core's own LookupController::save() (which still handles `units`); kept as
 * a small duplicate rather than a shared base class across the core/module
 * boundary for one reused method.
 *
 * Responds with JSON, never redirects — the modal must not blow away
 * whatever the surrounding product form already has typed into it.
 */
final class ProductTypeController extends Controller
{
    public function save(): void
    {
        csrf_verify();
        header('Content-Type: application/json; charset=utf-8');

        if (Auth::impersonating() !== null) {
            http_response_code(403);
            echo json_encode(['error' => terr('error.forbidden')], JSON_UNESCAPED_UNICODE);
            return;
        }

        $id        = trim((string) ($_POST['id'] ?? ''));
        $name      = trim((string) ($_POST['name'] ?? ''));
        $editingId = ctype_digit($id) ? (int) $id : null;

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => terr('ptype.err_name_required')], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($name) > 255) {
            http_response_code(422);
            echo json_encode(['error' => terr('cust.err_too_long', 255)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($editingId !== null) {
            ProductType::update($editingId, $name);
        } else {
            $editingId = ProductType::create($name);
        }

        echo json_encode(['id' => $editingId, 'name' => $name], JSON_UNESCAPED_UNICODE);
    }
}
