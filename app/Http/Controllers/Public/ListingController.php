<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListingSearchRequest;
use App\Models\Amenity;
use App\Models\Province;
use App\Queries\ListingSearchQuery;
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

        return view('public.listings.index', compact('listings', 'provinces', 'amenities'));
    }

    public function show(int $listing, ListingSearchQuery $listingSearchQuery): View
    {
        $listing = $listingSearchQuery->build()
            ->whereKey($listing)
            ->firstOrFail();

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

        return view('public.listings.show', compact('listing', 'coverImage', 'galleryImages', 'avatarSrc', 'initials'));
    }
}
