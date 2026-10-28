<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\Auth;
use App\Security\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

/**
 * Base controller with shared helpers.
 */
abstract class BaseController
{
    public function __construct(
        protected readonly View $view,
        protected readonly Auth $auth,
        protected readonly Csrf $csrf,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $user = $this->auth->user();
        $this->view->share('currentUser', $user);
        $this->view->share('csrf', $this->csrf);
        $this->view->share('flash', Session::allFlash());

        Response::html($this->view->render($template, $data, $layout));
    }

    protected function requireCsrf(Request $request): void
    {
        if ($this->csrf->validate($request->input('_csrf'))) {
            return;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = $request->isAjax() || str_contains($accept, 'application/json');
        if ($isAjax) {
            Response::json(['ok' => false, 'error' => __('flash.csrf_retry')], 419);
            exit;
        }

        Session::flash('error', __('flash.csrf_invalid'));
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
