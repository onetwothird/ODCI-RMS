<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Port of roles/admin/dashboard.php + roles/admin/script/dashboard.php.
 *
 * All figures are prepared by AdminDashboardData so the controller stays a
 * thin translation of the legacy page's include structure.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardData $data) {}

    /**
     * GET /admin/dashboard
     */
    public function index(Request $request): View
    {
        return view('admin.dashboard', $this->data->forUser($request->user()));
    }
}
