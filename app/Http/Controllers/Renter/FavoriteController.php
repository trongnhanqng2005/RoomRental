<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Queries\ListingSearchQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(Request $request, ListingSearchQuery $listingSearchQuery): View|RedirectResponse
    {
        $favorites = $request->user()->favorites()
            ->select([
                'listings.id',
                'listings.category_id',
                'listings.ward_id',
                'listings.title',
                'listings.monthly_rent',
                'listings.area_m2',
                'listings.deleted_at',
            ])
            ->with([
                'category:id,name',
                'coverImage:id,listing_id,image_url',
                'ward.district.province',
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate(10);

        if ($favorites->total() > 0 && $favorites->isEmpty() && $favorites->currentPage() > $favorites->lastPage()) {
            $lastPage = $favorites->lastPage();

            return redirect()->route('wishlist.index', $lastPage > 1 ? ['page' => $lastPage] : []);
        }

        $listingIds = $favorites->getCollection()->modelKeys();
        $publicListingIds = $listingIds === []
            ? []
            : $listingSearchQuery->build()->whereKey($listingIds)->pluck('listings.id')->map(fn ($id) => (int) $id)->all();
        $publicListingIdSet = array_fill_keys($publicListingIds, true);

        $favorites->getCollection()->each(function (Listing $listing) use ($publicListingIdSet): void {
            $listing->setAttribute('is_publicly_eligible', isset($publicListingIdSet[$listing->id]));
        });

        return view('renter.wishlist.index', compact('favorites'));
    }

    public function store(Request $request, int $listing, ListingSearchQuery $listingSearchQuery): RedirectResponse
    {
        $eligibleListing = $listingSearchQuery->build()->whereKey($listing)->firstOrFail();

        try {
            DB::table('favorites')->insert([
                'user_id' => $request->user()->getKey(),
                'listing_id' => $eligibleListing->getKey(),
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateFavoritePair($exception)) {
                throw $exception;
            }
        }

        return back();
    }

    public function destroy(Request $request, int $listing): RedirectResponse
    {
        $request->user()->favorites()->detach($listing);

        return back();
    }

    private function isDuplicateFavoritePair(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;

        return ($errorInfo[0] ?? null) === '23000'
            && (int) ($errorInfo[1] ?? 0) === 1062
            && preg_match('/for key [\'`](?:favorites\.)?PRIMARY[\'`]/i', (string) ($errorInfo[2] ?? '')) === 1;
    }
}
