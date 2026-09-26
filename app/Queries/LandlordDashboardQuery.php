<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class LandlordDashboardQuery
{
    /**
     * @return array{
     *     total_listings: int,
     *     available_rooms: int,
     *     rented_rooms: int,
     *     waiting_appointments: int,
     *     total_views: int
     * }
     */
    public function metrics(User $landlord): array
    {
        $listings = DB::table('listings')
            ->where('landlord_id', $landlord->id)
            ->whereNull('deleted_at')
            ->selectRaw(
                'COUNT(*) AS total_listings,
                COALESCE(SUM(CASE WHEN occupancy_status = ? THEN 1 ELSE 0 END), 0) AS available_rooms,
                COALESCE(SUM(CASE WHEN occupancy_status = ? THEN 1 ELSE 0 END), 0) AS rented_rooms,
                COALESCE(SUM(view_count), 0) AS total_views',
                ['AVAILABLE', 'RENTED'],
            )
            ->first();

        $waitingAppointments = DB::table('appointments')
            ->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')
            ->join('listings', 'listings.id', '=', 'viewing_slots.listing_id')
            ->where('listings.landlord_id', $landlord->id)
            ->whereNull('listings.deleted_at')
            ->where('appointments.status', 'PENDING')
            ->count('appointments.id');

        return [
            'total_listings' => (int) $listings->total_listings,
            'available_rooms' => (int) $listings->available_rooms,
            'rented_rooms' => (int) $listings->rented_rooms,
            'waiting_appointments' => $waitingAppointments,
            'total_views' => (int) $listings->total_views,
        ];
    }
}
