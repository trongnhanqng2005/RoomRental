<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListingSearchRequest;
use App\Models\Amenity;
use App\Models\Province;
use App\Queries\ListingSearchQuery;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ListingController extends Controller
{
    public function index(ListingSearchRequest $request, ListingSearchQuery $listingSearchQuery): View
    {
        $listings = $listingSearchQuery->build($request->validated())
            ->with([
                'category:id,name',
                'coverImage:id,listing_id,image_url',
                'ward.district.province',
                'amenities:id,name',
            ])
            ->paginate(10)
            ->withQueryString();

        $canFavorite = $request->user()?->hasRole('RENTER') ?? false;
        $favoritedListingIds = [];
        $listingIds = $listings->getCollection()->modelKeys();

        if ($canFavorite && $listingIds !== []) {
            $favoritedListingIds = $request->user()->favorites()
                ->whereIn('listings.id', $listingIds)
                ->pluck('listings.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $provinces = Province::query()
            ->where('is_active', true)
            ->with([
                'districts' => fn ($query) => $query->where('is_active', true)
                    ->with(['wards' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
        $amenities = Amenity::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('public.listings.index', compact('listings', 'provinces', 'amenities', 'canFavorite', 'favoritedListingIds'));
    }

    public function show(
        Request $request,
        int $listing,
        ListingSearchQuery $listingSearchQuery,
        AppointmentService $appointmentService,
    ): View {
        $listing = $listingSearchQuery->build()
            ->whereKey($listing)
            ->firstOrFail();

        $canFavorite = $request->user()?->hasRole('RENTER') ?? false;
        $isFavorited = $canFavorite && $request->user()->favorites()->whereKey($listing->getKey())->exists();

        DB::table('listings')->where('id', $listing->getKey())->increment('view_count');
        $listing->load([
            'category:id,name',
            'ward.district.province',
            'amenities:id,name',
            'fees.feeType:id,name',
            'fees.feeUnit:id,name',
            'images',
            'landlord:id,phone',
            'landlord.profile:user_id,full_name,avatar_url,zalo_number',
        ]);
        $bookableViewingSlots = $appointmentService->bookableSlotsForListing($listing);
        $listing->setRelation('bookableViewingSlots', $bookableViewingSlots);

        $coverImage = $listing->images->firstWhere('is_cover', true) ?? $listing->images->first();
        $galleryImages = $listing->images->reject(fn ($image) => $image->id === $coverImage?->id)->values();
        $avatarUrl = $listing->landlord->profile?->avatar_url;
        $avatarSrc = $avatarUrl === null
            ? null
            : (filter_var($avatarUrl, FILTER_VALIDATE_URL) ? $avatarUrl : asset(ltrim($avatarUrl, '/')));
        $initials = collect(preg_split('/\s+/u', trim($listing->landlord->profile?->full_name ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $user = $request->user();
        $canBookAppointment = $user !== null
            && $user->hasRole('RENTER')
            && (int) $listing->landlord_id !== (int) $user->id;

        return view('public.listings.show', compact(
            'listing',
            'coverImage',
            'galleryImages',
            'avatarSrc',
            'initials',
            'canFavorite',
            'isFavorited',
            'bookableViewingSlots',
            'canBookAppointment',
        ));
    }
}
