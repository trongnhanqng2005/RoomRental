<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAmenityRequest;
use App\Http\Requests\Admin\UpdateAmenityRequest;
use App\Models\Amenity;
use App\Services\CatalogAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AmenityController extends Controller
{
    public function index(): View
    {
        $amenities = Amenity::query()
            ->withCount('listings')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.amenities.index', compact('amenities'));
    }

    public function store(StoreAmenityRequest $request, CatalogAdministrationService $service): RedirectResponse
    {
        $service->createAmenity($request->validated(), $request->user());

        return redirect()->route('admin.amenities.index')->with('status', __('ui.catalogs.created'));
    }

    public function edit(Amenity $amenity): View
    {
        return view('admin.amenities.edit', compact('amenity'));
    }

    public function update(UpdateAmenityRequest $request, Amenity $amenity, CatalogAdministrationService $service): RedirectResponse
    {
        $service->updateAmenity($amenity, $request->validated(), $request->user());

        return redirect()->route('admin.amenities.index')->with('status', __('ui.catalogs.updated'));
    }

    public function hide(Amenity $amenity, CatalogAdministrationService $service): RedirectResponse
    {
        $service->setAmenityActive($amenity, false, request()->user());

        return redirect()->route('admin.amenities.index')->with('status', __('ui.catalogs.hidden_success'));
    }

    public function restore(Amenity $amenity, CatalogAdministrationService $service): RedirectResponse
    {
        $service->setAmenityActive($amenity, true, request()->user());

        return redirect()->route('admin.amenities.index')->with('status', __('ui.catalogs.restored_success'));
    }
}
