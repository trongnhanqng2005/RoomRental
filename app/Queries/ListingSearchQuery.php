<?php

namespace App\Queries;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

class ListingSearchQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(array $filters = []): Builder
    {
        $now = now();
        $expiryCutoff = $now->copy()->subDays(30);

        $query = Listing::query()
            ->whereNull('deleted_at')
            ->where('visibility_status', 'VISIBLE')
            ->where('occupancy_status', 'AVAILABLE')
            ->whereHas('currentModeration', fn (Builder $moderation) => $moderation->where('status', 'APPROVED'))
            ->where(function (Builder $expiry) use ($now, $expiryCutoff): void {
                $expiry->where('expires_at', '>', $now)
                    ->orWhere(function (Builder $unsetExpiry) use ($expiryCutoff): void {
                        $unsetExpiry->whereNull('expires_at')
                            ->whereHas('currentModeration', fn (Builder $moderation) => $moderation
                                ->where('status', 'APPROVED')
                                ->where('reviewed_at', '>', $expiryCutoff));
                    });
            });

        if (($keyword = $filters['q'] ?? null) !== null && $keyword !== '') {
            $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('street_address', 'like', '%'.$keyword.'%');
            });
        }

        if (isset($filters['province_id'])) {
            $query->whereHas('ward.district', fn (Builder $district) => $district->where('province_id', $filters['province_id']));
        }

        if (isset($filters['district_id'])) {
            $query->whereHas('ward', fn (Builder $ward) => $ward->where('district_id', $filters['district_id']));
        }

        if (isset($filters['ward_id'])) {
            $query->where('ward_id', $filters['ward_id']);
        }

        foreach ([
            'min_price' => ['monthly_rent', '>='],
            'max_price' => ['monthly_rent', '<='],
            'min_area' => ['area_m2', '>='],
            'max_area' => ['area_m2', '<='],
        ] as $filter => [$column, $operator]) {
            if (isset($filters[$filter])) {
                $query->where($column, $operator, $filters[$filter]);
            }
        }

        $amenityIds = array_values(array_unique(array_map('intval', $filters['amenity_ids'] ?? [])));

        if ($amenityIds !== []) {
            $query->whereHas('amenities', fn (Builder $amenities) => $amenities->whereIn('amenities.id', $amenityIds), '=', count($amenityIds));
        }

        if (isset($filters['gender_requirement'])) {
            $query->where(function (Builder $gender) use ($filters): void {
                if ($filters['gender_requirement'] === 'ANY') {
                    $gender->where('gender_requirement', 'ANY');

                    return;
                }

                $gender->whereIn('gender_requirement', [$filters['gender_requirement'], 'ANY']);
            });
        }

        return $this->applySort($query, $filters['sort'] ?? 'newest');
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('monthly_rent')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('monthly_rent')->orderByDesc('id'),
            'area_asc' => $query->orderBy('area_m2')->orderByDesc('id'),
            'area_desc' => $query->orderByDesc('area_m2')->orderByDesc('id'),
            'most_viewed' => $query->orderByDesc('view_count')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
