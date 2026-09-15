<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\ModuleArchive;
use App\Core\ModuleRegistry;

/**
 * The modules settings page (4.113). Four distinct acts, deliberately not
 * collapsed into one "remove" button:
 *
 *   upload    — a .zip lands in app/Modules/ (validated by ModuleArchive)
 *   install   — its migrations run; it becomes available server-wide
 *   enable    — one tenant switches it on for themselves
 *   uninstall — its data is exported to storage/backups/, then its tables go
 *
 * install/uninstall change the server and are admin-only; enable/disable are
 * per tenant, so each customer decides for their own account. Deleting a
 * module's files is a further separate step, so an uninstall can be undone by
 * installing again without re-uploading.
 *
 * Every write here is admin + CSRF + not-impersonating: a SuperUser browsing
 * someone else's tenant must not be able to change what that tenant has
 * installed.
 */
final class ModuleController extends Controller
{
    public function index(): void
    {
        $this->view('modules', [
            'title'    => t('page.modules') . ' · ' . app_name(),
            'modules'  => ModuleRegistry::summaries(),
            'failures' => ModuleRegistry::failures(),
            'flash'    => flash('modules_flash'),
            'error'    => flash('modules_error'),
        ]);
    }

    public function upload(): void
    {
        $this->guard();

        try {
            $code = ModuleArchive::installUpload($_FILES['module'] ?? []);
            flash('modules_flash', t('modules.uploaded', $code));
        } catch (\Throwable $e) {
            flash('modules_error', $e->getMessage());
        }

        redirect('/settings/modules');
    }

    public function install(): void
    {
        $this->guard();

        $code = $this->code();
        try {
            ModuleRegistry::install($code);
            flash('modules_flash', t('modules.installed', $code));
        } catch (\Throwable $e) {
            // A module's own migration failing must not look like success.
            flash('modules_error', t('modules.err_install', $code, $e->getMessage()));
        }

        redirect('/settings/modules');
    }

    /**
     * Exports the module's data first, then drops its tables. The flash names
     * the export file, so the person who just clicked it knows where their
     * data went — the confirmation dialog said this would happen.
     */
    public function uninstall(): void
    {
        $this->guard();

        $code = $this->code();
        try {
            $backup = ModuleArchive::exportData($code);
            ModuleRegistry::uninstall($code);

            flash('modules_flash', $backup !== null
                ? t('modules.uninstalled_with_backup', $code, basename($backup))
                : t('modules.uninstalled', $code));
        } catch (\Throwable $e) {
            flash('modules_error', t('modules.err_uninstall', $code, $e->getMessage()));
        }

        redirect('/settings/modules');
    }

    /** Uninstall first — this only deletes files, never data. */
    public function removeFiles(): void
    {
        $this->guard();

        $code = $this->code();
        if (ModuleRegistry::isInstalled($code)) {
            flash('modules_error', t('modules.err_uninstall_first', $code));
            redirect('/settings/modules');
        }

        ModuleArchive::removeFiles($code);
        flash('modules_flash', t('modules.files_removed', $code));
        redirect('/settings/modules');
    }

    public function enable(): void
    {
        $this->guard();
        ModuleRegistry::enable($this->code());
        redirect($this->backTo());
    }

    public function disable(): void
    {
        $this->guard();
        ModuleRegistry::disable($this->code());
        redirect($this->backTo());
    }

    private function guard(): void
    {
        Auth::requireAdmin();
        csrf_verify();
        Auth::requireNotImpersonating();
    }

    /** Validated here, not deeper: the code is a directory name downstream. */
    private function code(): string
    {
        $code = (string) ($_POST['code'] ?? '');
        if (!ModuleRegistry::isValidCode($code)) {
            flash('modules_error', t('modules.err_bad_code'));
            redirect('/settings/modules');
        }

        return $code;
    }

    /** Toggling from the topbar dropdown should stay on the current page. */
    private function backTo(): string
    {
        $to = (string) ($_POST['redirect'] ?? '');

        return str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : '/settings/modules';
    }
}
