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
            'title'             => t('superuser.title') . ' · ' . app_name(),
            'tenants'           => User::allGroupedByTenant(),
            'impersonating'     => Auth::impersonating(),
            // The specific person picked, not just the tenant — differs from
            // 'impersonating' only when SuperUser browsed as one particular
            // sub-user (4.86) — see superuser.php's per-row button styling.
            'impersonatingUser' => Auth::impersonatingUserId(),
        ]);
    }

    /**
     * "აქტივობა" (4.89) — every logged-in request's own trail (ActivityLog,
     * written from public/index.php's single choke point), optionally
     * narrowed to one person via the left-hand user picker (?user_id=N).
     * User::allGroupedByTenant() (superuser.php's own roster query, 4.85) —
     * not a flat list — so the picker can indent each tenant's sub-users
     * under their admin, same as that page already does.
     */
    public function activity(): void
    {
        Auth::requireSuperuser();

        $selectedUserId = ctype_digit((string) ($_GET['user_id'] ?? '')) ? (int) $_GET['user_id'] : null;

        $this->view('superuser-activity', [
            'title'          => t('superuser.activity_title') . ' · ' . app_name(),
            'tenantGroups'   => User::allGroupedByTenant(),
            'selectedUserId' => $selectedUserId,
            'entries'        => \App\Core\ActivityLog::all($selectedUserId),
        ]);
    }

    /**
     * $_POST['user_id'] is whichever row's "დათვალიერება" was clicked —
     * either a root tenant's own, or (4.86) one of its sub-users', from
     * superuser.php's child table. Either way Auth::tenantId() (ruler-scoped
     * data: customers/products/organization) still resolves to the ROOT
     * tenant — a sub-user shares their creator's data, they're not a tenant
     * of their own — while Auth::invoiceScopeUserIds() narrows invoice-scoped
     * views (orders.php, the dashboard) to just the specific person picked.
     */
    public function impersonate(): void
    {
        Auth::requireSuperuser();
        csrf_verify();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $target = User::findById($userId);

        if ($target === null || $target['role'] === 'superadmin') {
            flash('notice', terr('superuser.err_invalid_tenant'));
            redirect('/superuser');
        }

        $tenantId = $target['created_by'] !== null ? (int) $target['created_by'] : $userId;
        $tenant   = $target['created_by'] !== null ? User::findById($tenantId) : $target;

        // The root tenant behind $userId must itself be a real root admin —
        // not another superadmin, and (a sub-user's created_by always points
        // at one, but defend it anyway) not somehow another sub-user.
        if ($tenant === null || $tenant['created_by'] !== null || $tenant['role'] === 'superadmin') {
            flash('notice', terr('superuser.err_invalid_tenant'));
            redirect('/superuser');
        }

        Auth::impersonate($tenantId, $userId);
        redirect('/');
    }

    public function stop(): void
    {
        Auth::requireSuperuser();
        csrf_verify();

        Auth::stopImpersonating();
        redirect('/superuser');
    }

    /**
     * The one write action SuperUser is actually allowed — block or unblock
     * a tenant admin or one of their sub-users (superuser.php's per-row and
     * per-badge buttons). Everything else about a tenant is view-only while
     * impersonating (Auth::requireNotImpersonating()) — this route
     * deliberately isn't gated by that, it's SuperUser's own direct action,
     * not something done *as* the tenant.
     */
    public function toggleBlock(): void
    {
        Auth::requireSuperuser();
        csrf_verify();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $target = User::findById($userId);

        if ($target === null || $target['role'] === 'superadmin') {
            flash('notice', terr('superuser.err_invalid_tenant'));
            redirect('/superuser');
        }

        User::setBlocked($userId, $target['blocked_at'] === null);
        redirect('/superuser');
    }
}
