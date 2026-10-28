<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\DocsContent;
use App\Support\I18n;
use App\Support\Request;
use App\Support\Response;

/**
 * In-app user documentation (authenticated).
 */
final class DocsController extends BaseController
{
    public function index(Request $request): void
    {
        if (!$this->auth->check()) {
            Response::redirect('/login');
        }

        $locale = I18n::instance()->locale();
        $docs = DocsContent::forLocale($locale);

        $this->render('docs/index', [
            'pageTitle' => $docs['page_title'],
            'docs' => $docs,
        ]);
    }
}
