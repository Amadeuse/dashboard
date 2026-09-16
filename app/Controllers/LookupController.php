<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ProductType;
use App\Models\Unit;

/**
 * units/product_types are both id+name lookup tables, managed from a modal on
 * the products page (see products.php). Both are tenant-scoped now —
 * product_types since migrations/023, units since 039 (4.133) — and take the
 * same (name, ruler) arguments, so the two actions share one body again;
 * the split productTypes() used to carry existed only because units had no
 * ruler to pass.
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

    public function productTypes(): void
    {
        $this->save(ProductType::class, 'ptype.err_name_required');
    }

    /** @param class-string $model must expose create(string,int):int and update(int,string,int):int */
    private function save(string $model, string $requiredKey): void
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
            echo json_encode(['error' => terr($requiredKey)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (mb_strlen($name) > 255) {
            http_response_code(422);
            echo json_encode(['error' => terr('cust.err_too_long', 255)], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($editingId !== null) {
            // 0 rows = not this tenant's row (another tenant's id, or one of
            // units' shared defaults). Before 4.133 this returned {id, name}
            // as if it had worked — a false success on a foreign id.
            if ($model::update($editingId, $name, $ruler) === 0) {
                http_response_code(404);
                echo json_encode(['error' => terr('error.404')], JSON_UNESCAPED_UNICODE);
                return;
            }
        } else {
            $editingId = $model::create($name, $ruler);
        }

        echo json_encode(['id' => $editingId, 'name' => $name], JSON_UNESCAPED_UNICODE);
    }
}
