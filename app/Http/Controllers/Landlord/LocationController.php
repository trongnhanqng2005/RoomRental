<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function districts(Province $province): JsonResponse
    {
        abort_unless($province->is_active, 404);

        return response()->json($province->districts()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));
    }

    public function wards(District $district): JsonResponse
    {
        abort_unless($district->is_active && $district->province()->where('is_active', true)->exists(), 404);

        return response()->json($district->wards()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']));
    }
}
