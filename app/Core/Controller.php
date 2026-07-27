<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * Render a view inside the layout.
     * A view may assign $scripts; the layout picks it up from this same scope.
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require APP_PATH . '/Views/' . $view . '.php';
        $content = ob_get_clean();

        require APP_PATH . '/Views/layout.php';
    }
}
