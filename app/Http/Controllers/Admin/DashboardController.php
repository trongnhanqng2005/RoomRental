<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\AdminDashboardQuery;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardQuery $dashboardQuery): View
    {
        return view('admin.dashboard', $dashboardQuery->dashboardData());
    }
}
