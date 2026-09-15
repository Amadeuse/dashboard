<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

/**
 * The Help section. nav.help sat on '#' until 4.113 — the module author's
 * guide is what it finally needed a page for.
 *
 * The guide is a view rather than a Markdown file so it can show the app's
 * own live values (the hook points that actually exist, the real directory
 * layout) instead of a copy that drifts from them.
 */
final class HelpController extends Controller
{
    public function index(): void
    {
        $this->view('help/index', [
            'title' => t('page.help') . ' · ' . app_name(),
        ]);
    }

    public function modules(): void
    {
        $this->view('help/modules', [
            'title' => t('help.modules_title') . ' · ' . app_name(),
        ]);
    }
}
