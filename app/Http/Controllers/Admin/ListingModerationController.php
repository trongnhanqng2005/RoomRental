<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectListingModerationRequest;
use App\Models\Listing;
use App\Models\ListingModeration;
use App\Services\ListingLifecycleService;
use App\Services\ListingModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->string('sort', 'oldest')->toString();
        $direction = $sort === 'newest' ? 'desc' : 'asc';

        $listings = Listing::query()
            ->select('listings.*')
            ->join('listing_moderations', 'listings.current_moderation_id', '=', 'listing_moderations.id')
            ->whereNull('listings.deleted_at')
            ->where('listing_moderations.status', 'PENDING')
            ->with([
                'currentModeration',
                'landlord.profile',
                'category',
                'ward.district.province',
            ])
            ->orderBy('listing_moderations.submitted_at', $direction)
            ->orderBy('listing_moderations.id', $direction)
            ->paginate(10)
            ->withQueryString();

        return view('admin.listing-moderations.index', compact('listings', 'sort'));
    }

    public function show(Listing $listing): View
    {
        $listing->load([
            'currentModeration.reviewer.profile',
            'moderations.reviewer.profile',
            'landlord.profile',
            'category',
            'ward.district.province',
            'images',
            'amenities',
            'fees.feeType',
            'fees.feeUnit',
        ]);

        abort_if($listing->currentModeration === null, 404);

        return view('admin.listing-moderations.show', compact('listing'));
    }

    public function approve(
        Listing $listing,
        ListingModeration $moderation,
        ListingModerationService $moderationService,
    ): RedirectResponse {
        $moderationService->approve($listing, $moderation, request()->user());

        return redirect()
            ->route('admin.listing-moderations.show', $listing)
            ->with('status', __('ui.admin_moderation.approved'));
    }

    public function reject(
        RejectListingModerationRequest $request,
        Listing $listing,
        ListingModeration $moderation,
        ListingModerationService $moderationService,
    ): RedirectResponse {
        $moderationService->reject($listing, $moderation, $request->user(), $request->validated('rejection_reason'));

        return redirect()
            ->route('admin.listing-moderations.show', $listing)
            ->with('status', __('ui.admin_moderation.rejected'));
    }

    public function unsuspend(Listing $listing, ListingLifecycleService $lifecycleService): RedirectResponse
    {
        $lifecycleService->unsuspend($listing, request()->user());

        return back()->with('status', __('ui.listings.unsuspended'));
    }
}
