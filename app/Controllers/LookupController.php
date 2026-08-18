<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ProductType;
use App\Models\Unit;

/**
 * units/product_types are both bare id+name tables, managed from a modal on
 * the products page (see products.php). App\Modules\Warehouse\Controllers\
 * ProductTypeController has its own near-identical productTypes() action for
 * the same product_types table — duplicated rather than shared across the
 * core/module boundary, same reasoning as handoff.md 4.19.
 *
 * Unlike CustomerController::store(), this responds with JSON and never
 * redirects: the modal must not blow away whatever the surrounding product
 * form already has typed into it.
 *
 * productTypes() no longer shares save()'s generic body with units() —
 * product_types is multi-tenant (migrations/023, ruler = Auth::tenantId()),
 * units isn't, so ProductType::create()/update() need an extra argument
 * units' Unit::create()/update() don't. Small duplication, same call as
 * ProductType.php's own docblock (handoff.md 4.19's reasoning again).
 */
final class LookupController extends Controller
{
    public function units(): void
    {
        $this->save(Unit::class, 'unit.err_name_required');
    }

    public function productTypes(): void
    {
        csrf_verify();
        header('Content-Type: application/json; charset=utf-8');

        // SuperUser is read-only while impersonating (Auth::requireNotImpersonating()'s
        // docblock) — inline here, not that shared helper, since this responds
        // with JSON and must never exit with a plain-text 403 the modal's JS can't parse.
        if (Auth::impersonating() !== null) {
            http_response_code(403);
            echo json_encode(['error' => terr('error.forbidden')], JSON_UNESCAPED_UNICODE);
            return;
        }

        $ruler     = Auth::tenantId();
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
            ProductType::update($editingId, $name, $ruler);
        } else {
            $editingId = ProductType::create($name, $ruler);
        }

        echo json_encode(['id' => $editingId, 'name' => $name], JSON_UNESCAPED_UNICODE);
    }

    /** @param class-string $model must expose create(string):int and update(int,string):void */
    private function save(string $model, string $requiredKey): void
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
