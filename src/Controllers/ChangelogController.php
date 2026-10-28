<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ChangelogService;
use App\Support\Request;
use App\Support\Response;

/**
 * Expose le changelog applicatif (JSON) pour le lecteur du footer.
 */
final class ChangelogController extends BaseController
{
    public function api(Request $request, ChangelogService $changelog): void
    {
        $this->auth->requireLogin();

        Response::json([
            'ok' => true,
            'data' => $changelog->getDocument(),
        ]);
    }
}
