<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ErrorController extends Controller
{
    public function notFound(): void
    {
        $this->view('errors/404', ['title' => '404 · ' . app_name()]);
    }

    public function notAllowed(): void
    {
        $this->view('errors/405', ['title' => '405 · ' . app_name()]);
    }
}
