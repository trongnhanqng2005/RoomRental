<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectListingModerationRequest;
use App\Models\Listing;
use App\Models\ListingModeration;
use App\Services\ListingModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public function index(): View
    {
        $listings = Listing::query()
            ->whereNull('deleted_at')
            ->whereHas('currentModeration', fn ($query) => $query->where('status', 'PENDING'))
            ->with([
                'currentModeration',
                'landlord.profile',
                'category',
                'ward.district.province',
            ])
            ->orderByDesc('updated_at')
            ->paginate(10);

        return view('admin.listing-moderations.index', compact('listings'));
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
}
