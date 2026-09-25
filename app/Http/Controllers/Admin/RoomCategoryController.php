<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomCategoryRequest;
use App\Http\Requests\Admin\UpdateRoomCategoryRequest;
use App\Models\RoomCategory;
use App\Services\CatalogAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomCategoryController extends Controller
{
    public function index(): View
    {
        $categories = RoomCategory::query()
            ->withCount('listings')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function store(StoreRoomCategoryRequest $request, CatalogAdministrationService $service): RedirectResponse
    {
        $service->createCategory($request->validated(), $request->user());

        return redirect()->route('admin.categories.index')->with('status', __('ui.catalogs.created'));
    }

    public function edit(RoomCategory $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(UpdateRoomCategoryRequest $request, RoomCategory $category, CatalogAdministrationService $service): RedirectResponse
    {
        $service->updateCategory($category, $request->validated(), $request->user());

        return redirect()->route('admin.categories.index')->with('status', __('ui.catalogs.updated'));
    }

    public function hide(RoomCategory $category, CatalogAdministrationService $service): RedirectResponse
    {
        $service->setCategoryActive($category, false, request()->user());

        return redirect()->route('admin.categories.index')->with('status', __('ui.catalogs.hidden_success'));
    }

    public function restore(RoomCategory $category, CatalogAdministrationService $service): RedirectResponse
    {
        $service->setCategoryActive($category, true, request()->user());

        return redirect()->route('admin.categories.index')->with('status', __('ui.catalogs.restored_success'));
    }
}
