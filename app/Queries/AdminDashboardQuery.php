<?php

namespace App\Queries;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Models\ViewingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardQuery
{
    /** @return array<string, mixed> */
    public function dashboardData(): array
    {
        return [
            'metrics' => [
                'total_users' => User::query()->count(),
                'total_landlords' => DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('roles.code', 'LANDLORD')
                    ->distinct()
                    ->count('user_roles.user_id'),
                'pending_reports' => Report::query()->where('status', 'PENDING')->count(),
                'today_appointments' => $this->todayAppointments(),
            ],
            'listingStates' => $this->listingStates(),
            'monthlyListings' => $this->monthlyListings(),
            'categoryDistribution' => $this->categoryDistribution(),
            'recentPendingModeration' => $this->recentPendingModeration(),
            'recentPendingReports' => $this->recentPendingReports(),
        ];
    }

    /** @return array{total_listings: int, moderation: array<string, int>, occupancy: array<string, int>, visibility: array<string, int>} */
    private function listingStates(): array
    {
        $counts = DB::table('listings')
            ->leftJoin('listing_moderations as current_moderation', function ($join): void {
                $join->on('current_moderation.id', '=', 'listings.current_moderation_id')
                    ->on('current_moderation.listing_id', '=', 'listings.id');
            })
            ->whereNull('listings.deleted_at')
            ->selectRaw('COUNT(listings.id) AS total_listings')
            ->selectRaw("SUM(CASE WHEN current_moderation.status = 'PENDING' THEN 1 ELSE 0 END) AS moderation_pending")
            ->selectRaw("SUM(CASE WHEN current_moderation.status = 'APPROVED' THEN 1 ELSE 0 END) AS moderation_approved")
            ->selectRaw("SUM(CASE WHEN current_moderation.status = 'REJECTED' THEN 1 ELSE 0 END) AS moderation_rejected")
            ->selectRaw("SUM(CASE WHEN listings.occupancy_status = 'AVAILABLE' THEN 1 ELSE 0 END) AS occupancy_available")
            ->selectRaw("SUM(CASE WHEN listings.occupancy_status = 'RENTED' THEN 1 ELSE 0 END) AS occupancy_rented")
            ->selectRaw("SUM(CASE WHEN listings.visibility_status = 'VISIBLE' THEN 1 ELSE 0 END) AS visibility_visible")
            ->selectRaw("SUM(CASE WHEN listings.visibility_status = 'HIDDEN' THEN 1 ELSE 0 END) AS visibility_hidden")
            ->selectRaw("SUM(CASE WHEN listings.visibility_status = 'SUSPENDED' THEN 1 ELSE 0 END) AS visibility_suspended")
            ->first();

        return [
            'total_listings' => (int) $counts->total_listings,
            'moderation' => [
                'PENDING' => (int) $counts->moderation_pending,
                'APPROVED' => (int) $counts->moderation_approved,
                'REJECTED' => (int) $counts->moderation_rejected,
            ],
            'occupancy' => [
                'AVAILABLE' => (int) $counts->occupancy_available,
                'RENTED' => (int) $counts->occupancy_rented,
            ],
            'visibility' => [
                'VISIBLE' => (int) $counts->visibility_visible,
                'HIDDEN' => (int) $counts->visibility_hidden,
                'SUSPENDED' => (int) $counts->visibility_suspended,
            ],
        ];
    }

    private function todayAppointments(): int
    {
        $today = CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString();

        return DB::table('appointments')
            ->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')
            ->where('viewing_slots.viewing_date', $today)
            ->whereIn('appointments.status', ['PENDING', 'ACCEPTED', 'COMPLETED'])
            ->count('appointments.id');
    }

    /** @return array<int, array{month: string, label: string, count: int}> */
    private function monthlyListings(): array
    {
        $currentMonth = CarbonImmutable::now('UTC')->startOfMonth();
        $firstMonth = $currentMonth->subMonths(11);
        $end = $currentMonth->addMonth();
        $counts = DB::table('listings')
            ->whereRaw("created_at >= CONVERT_TZ(?, '+00:00', @@session.time_zone)", [$firstMonth->toDateTimeString()])
            ->whereRaw("created_at < CONVERT_TZ(?, '+00:00', @@session.time_zone)", [$end->toDateTimeString()])
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, @@session.time_zone, '+00:00'), '%Y-%m') AS month, COUNT(*) AS listing_count")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('listing_count', 'month');

        return collect(range(0, 11))
            ->map(function (int $offset) use ($firstMonth, $counts): array {
                $month = $firstMonth->addMonths($offset);
                $key = $month->format('Y-m');

                return [
                    'month' => $key,
                    'label' => $month->format('m/Y'),
                    'count' => (int) $counts->get($key, 0),
                ];
            })
            ->all();
    }

    /** @return array{total_listings: int, categories: array<int, array{category_id: int|null, name: string|null, is_active: bool|null, count: int, percentage: float}>} */
    private function categoryDistribution(): array
    {
        $groups = DB::table('listings')
            ->leftJoin('room_categories', 'room_categories.id', '=', 'listings.category_id')
            ->whereNull('listings.deleted_at')
            ->select('listings.category_id', 'room_categories.name', 'room_categories.is_active')
            ->selectRaw('COUNT(*) AS listing_count')
            ->groupBy('listings.category_id', 'room_categories.name', 'room_categories.is_active')
            ->orderBy('room_categories.name')
            ->orderBy('listings.category_id')
            ->get();
        $total = (int) $groups->sum('listing_count');

        return [
            'total_listings' => $total,
            'categories' => $groups->map(function (object $group) use ($total): array {
                $count = (int) $group->listing_count;

                return [
                    'category_id' => $group->category_id === null ? null : (int) $group->category_id,
                    'name' => $group->name,
                    'is_active' => $group->is_active === null ? null : (bool) $group->is_active,
                    'count' => $count,
                    'percentage' => $total === 0 ? 0.0 : round($count / $total * 100, 1),
                ];
            })->all(),
        ];
    }

    /** @return Collection<int, Listing> */
    private function recentPendingModeration(): Collection
    {
        return Listing::query()
            ->select(['listings.id', 'listings.title', 'listings.current_moderation_id'])
            ->join('listing_moderations as current_moderation', function ($join): void {
                $join->on('current_moderation.id', '=', 'listings.current_moderation_id')
                    ->on('current_moderation.listing_id', '=', 'listings.id')
                    ->where('current_moderation.status', 'PENDING');
            })
            ->whereNull('listings.deleted_at')
            ->with([
                'currentModeration:id,listing_id,status,submitted_at',
            ])
            ->orderByDesc('current_moderation.submitted_at')
            ->orderByDesc('listings.id')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Report> */
    private function recentPendingReports(): Collection
    {
        return Report::query()
            ->select(['id', 'listing_id', 'reason_id', 'created_at'])
            ->where('status', 'PENDING')
            ->with([
                'listing:id,title,deleted_at',
                'reason:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }
}
