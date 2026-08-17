<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Render a core view (app/Views/<name>.php) inside the layout.
     * A view may assign $scripts; the layout picks it up from this same scope.
     */
    protected function view(string $view, array $data = []): void
    {
        $this->render(APP_PATH . '/Views/' . $view . '.php', $data, APP_PATH . '/Views/layout.php');
    }

    /** Same rendering, for a view file that isn't under app/Views/ — a module's own. */
    protected function viewAt(string $file, array $data = []): void
    {
        $this->render($file, $data, APP_PATH . '/Views/layout.php');
    }

    /** Renders without the sidebar/topbar chrome — standalone public pages like auth. */
    protected function bare(string $view, array $data = []): void
    {
        $this->render(APP_PATH . '/Views/' . $view . '.php', $data, APP_PATH . '/Views/auth/_layout.php');
    }

    /**
     * Same view-rendering as view(), but with no layout at all and returned
     * as a string instead of echoed — for content meant to go somewhere
     * other than the browser's own page, e.g. App\Core\Pdf::download()'s
     * HTML-to-PDF input (mPDF's CSS support doesn't cover Bootstrap's grid/
     * flex either way, so these views are always their own plain markup).
     */
    protected function renderToString(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require APP_PATH . '/Views/' . $view . '.php';

        return ob_get_clean();
    }

    private function render(string $file, array $data, string $layout): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = ob_get_clean();

        require $layout;
    }
}
