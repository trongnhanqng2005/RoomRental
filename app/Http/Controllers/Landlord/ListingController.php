<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\StoreListingRequest;
use App\Http\Requests\Listing\UpdateListingOccupancyRequest;
use App\Http\Requests\Listing\UpdateListingRequest;
use App\Http\Requests\Listing\UpdateListingVisibilityRequest;
use App\Models\Amenity;
use App\Models\FeeType;
use App\Models\FeeUnit;
use App\Models\Listing;
use App\Models\Province;
use App\Models\RoomCategory;
use App\Services\ListingLifecycleService;
use App\Services\ListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(Request $request, ListingLifecycleService $lifecycleService): View
    {
        Gate::authorize('viewAny', Listing::class);

        $user = $request->user();
        $query = Listing::query()
            ->where('landlord_id', $user->id)
            ->whereNull('deleted_at')
            ->with(['category', 'coverImage', 'currentModeration', 'ward.district.province']);

        if ($request->filled('q')) {
            $query->where(function ($query) use ($request): void {
                $keyword = $request->string('q')->trim()->toString();
                $query->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('street_address', 'like', '%'.$keyword.'%');
            });
        }

        foreach (['occupancy_status', 'visibility_status'] as $status) {
            if ($request->filled($status)) {
                $query->where($status, $request->string($status)->toString());
            }
        }

        if ($request->filled('moderation_status')) {
            $query->whereHas('currentModeration', fn ($query) => $query->where('status', $request->string('moderation_status')->toString()));
        }

        $sort = $request->string('sort', 'updated_at')->toString();
        $sortColumn = match ($sort) {
            'rent_asc' => ['monthly_rent', 'asc'],
            'rent_desc' => ['monthly_rent', 'desc'],
            'oldest' => ['created_at', 'asc'],
            default => ['updated_at', 'desc'],
        };

        $listings = $query->orderBy(...$sortColumn)->paginate(10)->withQueryString();
        $listings->getCollection()->each(function (Listing $listing) use ($lifecycleService, $user): void {
            $listing->setAttribute('can_renew', $lifecycleService->canRenew($listing, $user));
            $listing->setAttribute('effective_expires_at', $listing->effectiveExpiresAt());
            $listing->setAttribute('is_expired', $listing->isExpired());
        });
        $statsQuery = Listing::query()->where('landlord_id', $user->id)->whereNull('deleted_at');

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->whereHas('currentModeration', fn ($query) => $query->where('status', 'PENDING'))->count(),
            'available' => (clone $statsQuery)->where('occupancy_status', 'AVAILABLE')->count(),
            'views' => (clone $statsQuery)->sum('view_count'),
        ];

        return view('landlord.listings.index', compact('listings', 'stats'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Listing::class);

        return view('landlord.listings.create', $this->formData($request));
    }

    public function store(StoreListingRequest $request, ListingService $listingService): RedirectResponse
    {
        $listing = $listingService->create($request->user(), $request->validated());
        $redirect = redirect()->route('landlord.listings.index')->with('status', __('ui.listings.created'));

        if ($listingService->findDuplicateAddress($listing)) {
            $redirect->with('duplicate_warning', __('ui.listings.duplicate_address_warning'));
        }

        return $redirect;
    }

    public function edit(Request $request, Listing $listing): View
    {
        Gate::authorize('update', $listing);
        $listing->load(['category', 'ward.district.province', 'images', 'amenities', 'fees.feeType', 'fees.feeUnit', 'currentModeration']);

        return view('landlord.listings.edit', array_merge(
            $this->formData($request),
            ['listing' => $listing],
        ));
    }

    public function update(UpdateListingRequest $request, Listing $listing, ListingService $listingService): RedirectResponse
    {
        $updated = $listingService->update($listing, $request->validated());
        $redirect = redirect()->route('landlord.listings.index')->with('status', __('ui.listings.updated'));

        if ($listingService->findDuplicateAddress($updated)) {
            $redirect->with('duplicate_warning', __('ui.listings.duplicate_address_warning'));
        }

        return $redirect;
    }

    public function updateOccupancy(UpdateListingOccupancyRequest $request, Listing $listing, ListingService $listingService): RedirectResponse
    {
        $listingService->updateOccupancy($listing, $request->validated('occupancy_status'), $request->user());

        return back()->with('status', __('ui.listings.occupancy_updated'));
    }

    public function updateVisibility(UpdateListingVisibilityRequest $request, Listing $listing, ListingService $listingService): RedirectResponse
    {
        $listingService->updateVisibility($listing, $request->validated('visibility_status'));

        return back()->with('status', __('ui.listings.visibility_updated'));
    }

    public function renew(Request $request, Listing $listing, ListingLifecycleService $lifecycleService): RedirectResponse
    {
        Gate::authorize('renew', $listing);
        $lifecycleService->renew($listing, $request->user());

        return back()->with('status', __('ui.listings.renewed'));
    }

    public function destroy(Request $request, Listing $listing, ListingLifecycleService $lifecycleService): RedirectResponse
    {
        Gate::authorize('delete', $listing);
        $lifecycleService->delete($listing, $request->user());

        return redirect()->route('landlord.listings.index')->with('status', __('ui.listings.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $listing = $request->route('listing');

        if ($listing instanceof Listing) {
            $selectedProvinceId = $listing->ward?->district?->province_id;
            $selectedDistrictId = $listing->ward?->district_id;
        } else {
            $selectedProvinceId = null;
            $selectedDistrictId = null;
        }

        return [
            'categories' => RoomCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('name')->get(),
            'feeTypes' => FeeType::query()->where('is_active', true)->orderBy('id')->get(),
            'feeUnits' => FeeUnit::query()->where('is_active', true)->orderBy('id')->get(),
            'provinces' => Province::query()
                ->where('is_active', true)
                ->with(['districts' => fn ($query) => $query->where('is_active', true)->with(['wards' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'selectedProvinceId' => old('province_id', $selectedProvinceId),
            'selectedDistrictId' => old('district_id', $selectedDistrictId),
        ];
    }
}
