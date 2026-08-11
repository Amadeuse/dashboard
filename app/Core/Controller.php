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

    private function render(string $file, array $data, string $layout): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = ob_get_clean();

        require $layout;
    }
}
