<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ModuleRegistry;

/**
 * Install/enable/disable for whatever's in app/Modules/*\/module.json.
 * "Install" is local-admin only — the module's files are assumed already on
 * disk (placed there out-of-band); this just runs its migrations and starts
 * tracking it. No uninstall (destructive, deferred) and no license/purchase
 * check (deferred — see handoff.md).
 *
 * `modules` has no `ruler` column (see migrations/003) — enabling/disabling
 * one affects every tenant in the whole app, not just the caller's own.
 * These three actions had no Auth::requireAdmin() at all until now (any
 * logged-in user, including a non-admin sub-user, could toggle a
 * system-wide module) — a real pre-existing gap, fixed alongside adding
 * the impersonation block below, since both land on the same lines.
 */
final class ModuleController extends Controller
{
    public function index(): void
    {
        $this->view('modules', [
            'title'   => t('page.modules') . ' · ' . app_name(),
            'modules' => ModuleRegistry::summaries(),
            'flash'   => flash('modules_flash'),
        ]);
    }

    public function install(): void
    {
        Auth::requireAdmin();
        csrf_verify();
        Auth::requireNotImpersonating();

        $code = (string) ($_POST['code'] ?? '');
        ModuleRegistry::install($code);
        flash('modules_flash', t('modules.installed', $code));
        redirect('/settings/modules');
    }

    public function enable(): void
    {
        Auth::requireAdmin();
        csrf_verify();
        Auth::requireNotImpersonating();

        ModuleRegistry::enable((string) ($_POST['code'] ?? ''));
        redirect($this->backTo());
    }

    public function disable(): void
    {
        Auth::requireAdmin();
        csrf_verify();
        Auth::requireNotImpersonating();

        ModuleRegistry::disable((string) ($_POST['code'] ?? ''));
        redirect($this->backTo());
    }

    /** Toggling from the topbar apps dropdown should stay on the current page, not jump to /settings/modules. */
    private function backTo(): string
    {
        $to = (string) ($_POST['redirect'] ?? '');

        return str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/settings/modules';
    }
}
