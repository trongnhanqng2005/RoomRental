<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\RoomCategory;
use App\Services\UserAdministrationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteManagementController extends Controller
{
    public function index(Request $request, UserAdministrationService $userService): View
    {
        $tab = $request->query('tab', 'users');
        if (! in_array($tab, ['users', 'categories', 'amenities'], true)) {
            $tab = 'users';
        }

        $filters = $request->only(['q', 'role', 'status']);
        $users = null;
        $categories = null;
        $amenities = null;

        if ($tab === 'users') {
            $users = $userService->paginate($filters);
            $users->appends(['tab' => 'users']);
        } elseif ($tab === 'categories') {
            $categories = RoomCategory::query()
                ->withCount('listings')
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        } elseif ($tab === 'amenities') {
            $amenities = Amenity::query()
                ->withCount('listings')
                ->orderBy('name')
                ->orderBy('id')
                ->get();
        }

        return view('admin.site-management.index', [
            'activeTab' => $tab,
            'users' => $users,
            'filters' => $filters,
            'categories' => $categories,
            'amenities' => $amenities,
        ]);
    }
}
