<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Queries\LandlordDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, LandlordDashboardQuery $dashboardQuery): View
    {
        return view('landlord.dashboard', [
            'metrics' => $dashboardQuery->metrics($request->user()),
        ]);
    }
}
