<?php

declare(strict_types=1);

namespace App\Modules\ModuleTemplate\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Modules\ModuleTemplate\Models\TemplateItem;

/**
 * Routes registered in Module.php land here. Every path is already under
 * /m/moduletemplate/ — ModuleRouter puts it there and a module cannot opt out.
 *
 * Three things every module controller owes, none of which core does for you:
 *   Auth::requireUser()  — there is an app-wide login gate, but a page that
 *                          needs a user should say so rather than assume;
 *   csrf_verify()        — on every POST, without exception;
 *   ownership            — the model filters on ruler, so a wrong id returns
 *                          null instead of someone else's row.
 */
final class TemplateController extends Controller
{
    public function index(): void
    {
        Auth::requireUser();

        // viewAt(), not view(): view() looks under app/Views/, which is core's.
        $this->viewAt(__DIR__ . '/../Views/list.php', [
            'title'  => t('tpl.page_title') . ' · ' . app_name(),
            'items'  => TemplateItem::all(),
            'errors' => flash('tpl_errors') ?? [],
            'old'    => flash('tpl_old') ?? [],
            'saved'  => flash('tpl_saved'),
        ]);
    }

    public function save(): void
    {
        Auth::requireUser();
        csrf_verify();

        $data   = ['label' => trim($_POST['label'] ?? ''), 'note' => trim($_POST['note'] ?? '')];
        $errors = TemplateItem::validate($data);

        if ($errors !== []) {
            // Flash the input back so the form can be re-filled — the same
            // errors/old convention core's own forms use.
            flash('tpl_errors', $errors);
            flash('tpl_old', $data);
            redirect('/m/moduletemplate/list');
        }

        $id = (int) ($_POST['id'] ?? 0);
        TemplateItem::save($data, $id > 0 ? $id : null);

        flash('tpl_saved', t('tpl.saved'));
        redirect('/m/moduletemplate/list');
    }

    public function delete(): void
    {
        Auth::requireUser();
        csrf_verify();

        TemplateItem::delete((int) ($_POST['id'] ?? 0));
        redirect('/m/moduletemplate/list');
    }
}
