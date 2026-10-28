<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DashboardService;
use App\Support\Request;
use App\Support\Response;

/**
 * Home dashboard.
 */
final class DashboardController extends BaseController
{
    public function index(Request $request, DashboardService $dashboard): void
    {
        if (!$this->auth->check()) {
            Response::redirect('/welcome');
        }
        $summary = $dashboard->summary();
        $this->render('dashboard/index', ['summary' => $summary]);
    }
}
