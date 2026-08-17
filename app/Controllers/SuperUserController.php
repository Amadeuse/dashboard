<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;

/**
 * /superuser — the cross-tenant roster (every tenant + their sub-users) and
 * the "browse as this tenant" switch. Auth::requireSuperuser() gates all
 * three actions: role === 'superadmin' only, everyone else bounced to /.
 *
 * Impersonation itself lives in Auth (impersonate()/stopImpersonating()/
 * impersonating()) — this controller only validates *which* tenant id is
 * being requested before handing it to Auth, and redirects.
 */
final class SuperUserController extends Controller
{
    public function index(): void
    {
        Auth::requireSuperuser();

        $this->view('superuser', [
            'title'         => t('superuser.title') . ' · ' . app_name(),
            'tenants'       => User::allGroupedByTenant(),
            'impersonating' => Auth::impersonating(),
        ]);
    }

    public function impersonate(): void
    {
        Auth::requireSuperuser();
        csrf_verify();

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $tenant   = User::findById($tenantId);

        // Only a real root tenant (an admin with no creator of their own) is a
        // valid impersonation target — not a sub-user id (Auth::tenantId()'s
        // impersonation branch trusts this value as-is, it doesn't re-resolve
        // through created_by the way it does for a normally logged-in sub-user)
        // and not another superadmin.
        if ($tenant === null || $tenant['created_by'] !== null || $tenant['role'] === 'superadmin') {
            flash('notice', terr('superuser.err_invalid_tenant'));
            redirect('/superuser');
        }

        Auth::impersonate($tenantId);
        redirect('/');
    }

    public function stop(): void
    {
        Auth::requireSuperuser();
        csrf_verify();

        Auth::stopImpersonating();
        redirect('/superuser');
    }
}
