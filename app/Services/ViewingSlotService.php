<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Listing;
use App\Models\User;
use App\Models\ViewingSlot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewingSlotService
{
    /**
     * @param  array{viewing_date: string, start_time: string, end_time: string}  $data
     */
    public function create(Listing $listing, User $actor, array $data): ViewingSlot
    {
        try {
            return DB::transaction(function () use ($listing, $actor, $data): ViewingSlot {
                $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
                $this->authorizeListing($lockedListing, $actor);
                $start = $this->localDateTime($data['viewing_date'], $data['start_time']);
                $end = $this->localDateTime($data['viewing_date'], $data['end_time']);

                if ($start->lessThanOrEqualTo(Carbon::now(ViewingSlot::TIMEZONE))) {
                    throw ValidationException::withMessages(['viewing_date' => __('validation.viewing_slot_future')]);
                }

                if (! $start->lessThan($end)) {
                    throw ValidationException::withMessages(['end_time' => __('validation.viewing_slot_time_order')]);
                }

                $duplicate = $lockedListing->viewingSlots()
                    ->whereDate('viewing_date', $data['viewing_date'])
                    ->whereTime('start_time', $data['start_time'])
                    ->whereTime('end_time', $data['end_time'])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['start_time' => __('validation.viewing_slot_duplicate')]);
                }

                if ($this->hasOpenOverlap($lockedListing->id, $data['viewing_date'], $data['start_time'], $data['end_time'])) {
                    throw ValidationException::withMessages(['start_time' => __('validation.viewing_slot_overlap')]);
                }

                return $lockedListing->viewingSlots()->create([
                    'viewing_date' => $data['viewing_date'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'status' => 'OPEN',
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'viewing_slots_listing_schedule_unique')) {
                throw ValidationException::withMessages(['start_time' => __('validation.viewing_slot_duplicate')]);
            }

            throw $exception;
        }
    }

    public function changeStatus(Listing $listing, ViewingSlot $slot, User $actor, string $status): void
    {
        if (! in_array($status, ['OPEN', 'CLOSED'], true)) {
            throw ValidationException::withMessages(['status' => __('validation.in', ['attribute' => __('ui.appointments.slot_status')])]);
        }

        DB::transaction(function () use ($listing, $slot, $actor, $status): void {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $this->authorizeListing($lockedListing, $actor);
            $lockedSlot = ViewingSlot::query()
                ->where('listing_id', $lockedListing->id)
                ->whereKey($slot->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSlot->status === $status) {
                return;
            }

            $hasActiveAppointment = $lockedSlot->appointments()
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->exists();

            if ($status === 'CLOSED' && $hasActiveAppointment) {
                throw ValidationException::withMessages(['status' => __('validation.viewing_slot_active_cannot_close')]);
            }

            if ($status === 'OPEN') {
                if (! $lockedSlot->startAtVietnam()->lessThan($lockedSlot->endAtVietnam())) {
                    throw ValidationException::withMessages(['status' => __('validation.viewing_slot_time_order')]);
                }

                if ($lockedSlot->startAtVietnam()->lessThanOrEqualTo(Carbon::now(ViewingSlot::TIMEZONE))) {
                    throw ValidationException::withMessages(['status' => __('validation.viewing_slot_reopen_future')]);
                }

                if ($hasActiveAppointment) {
                    throw ValidationException::withMessages(['status' => __('validation.viewing_slot_active_cannot_open')]);
                }

                if ($this->hasOpenOverlap(
                    $lockedListing->id,
                    $lockedSlot->viewing_date->toDateString(),
                    $lockedSlot->start_time,
                    $lockedSlot->end_time,
                    $lockedSlot->id,
                )) {
                    throw ValidationException::withMessages(['status' => __('validation.viewing_slot_overlap')]);
                }
            }

            $lockedSlot->status = $status;
            $lockedSlot->save();
        });
    }

    private function authorizeListing(Listing $listing, User $actor): void
    {
        if (
            ! $actor->hasRole('LANDLORD')
            || (int) $listing->landlord_id !== (int) $actor->id
        ) {
            throw new AuthorizationException;
        }
    }

    private function hasOpenOverlap(
        int $listingId,
        string $date,
        string $start,
        string $end,
        ?int $exceptSlotId = null,
    ): bool {
        return ViewingSlot::query()
            ->where('listing_id', $listingId)
            ->whereDate('viewing_date', $date)
            ->where('status', 'OPEN')
            ->whereTime('start_time', '<', $end)
            ->whereTime('end_time', '>', $start)
            ->when($exceptSlotId, fn ($query) => $query->whereKeyNot($exceptSlotId))
            ->exists();
    }

    private function localDateTime(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, ViewingSlot::TIMEZONE);
    }
}
