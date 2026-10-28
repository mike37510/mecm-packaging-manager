<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Support\WelcomeContent;

/**
 * Page publique de présentation (invités uniquement).
 */
final class WelcomeController extends BaseController
{
    public function index(Request $request, ?string $lang = null): void
    {
        if ($this->auth->check()) {
            Response::redirect('/');
        }

        $locale = ($lang !== null && strtolower($lang) === 'en') ? 'en' : 'fr';
        $copy = WelcomeContent::forLocale($locale);

        $this->render('welcome/index', [
            'pageTitle' => $copy['page_title'],
            'metaDescription' => $copy['meta_description'],
            'locale' => $locale,
            't' => $copy,
            'isLoggedIn' => false,
        ], 'layouts/presentation');
    }
}
