<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Listing;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Queries\ListingSearchQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class AppointmentService
{
    public function __construct(private ListingSearchQuery $listingSearchQuery) {}

    /** @return Collection<int, ViewingSlot> */
    public function bookableSlotsForListing(Listing $listing): Collection
    {
        [$earliest, $latest] = $this->bookingWindow();

        return ViewingSlot::query()
            ->where('listing_id', $listing->id)
            ->where('status', 'OPEN')
            ->whereColumn('start_time', '<', 'end_time')
            ->whereRaw('TIMESTAMP(viewing_date, start_time) >= ?', [$earliest->format('Y-m-d H:i:s')])
            ->whereRaw('TIMESTAMP(viewing_date, start_time) <= ?', [$latest->format('Y-m-d H:i:s')])
            ->whereDoesntHave('appointments', fn ($query) => $query->whereIn('status', Appointment::ACTIVE_STATUSES))
            ->orderBy('viewing_date')
            ->orderBy('start_time')
            ->get();
    }

    public function book(ViewingSlot $requestedSlot, User $renter, ?string $renterNote): Appointment
    {
        if ($renterNote !== null && mb_strlen($renterNote) > 1000) {
            throw ValidationException::withMessages(['renter_note' => __('validation.max.string', ['attribute' => __('ui.appointments.renter_note'), 'max' => 1000])]);
        }

        $listingId = ViewingSlot::query()->whereKey($requestedSlot->id)->value('listing_id');

        if ($listingId === null) {
            abort(404);
        }

        try {
            $result = DB::transaction(function () use ($requestedSlot, $renter, $renterNote, $listingId): array {
                $listing = Listing::query()->lockForUpdate()->findOrFail($listingId);
                $slot = ViewingSlot::query()
                    ->where('listing_id', $listing->id)
                    ->whereKey($requestedSlot->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeRenter($renter);

                if ($this->isPubliclyEligible($listing->id) === false) {
                    throw ValidationException::withMessages(['slot' => __('validation.appointment_slot_unavailable')]);
                }

                if ($slot->status !== 'OPEN') {
                    throw ValidationException::withMessages(['slot' => __('validation.appointment_slot_unavailable')]);
                }

                $this->ensureWithinBookingWindow($slot);

                if ((int) $listing->landlord_id === (int) $renter->id) {
                    throw ValidationException::withMessages(['slot' => __('validation.appointment_slot_unavailable')]);
                }

                if ($slot->appointments()->whereIn('status', Appointment::ACTIVE_STATUSES)->exists()) {
                    throw new ConflictHttpException(__('ui.appointments.active_conflict'));
                }

                $appointment = $slot->appointments()->create([
                    'renter_id' => $renter->id,
                    'status' => 'PENDING',
                    'renter_note' => $renterNote,
                ]);

                return [
                    'appointment' => $appointment,
                    'notification' => $this->notificationContext($appointment, $listing),
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'appointments_one_active_per_slot_unique')) {
                throw new ConflictHttpException(__('ui.appointments.active_conflict'));
            }

            throw $exception;
        }

        $this->notify($result['notification'], $result['notification']['landlord_id'], 'APPOINTMENT_BOOKED');

        return $result['appointment'];
    }

    public function accept(Appointment $appointment, User $landlord, ?string $response): void
    {
        $this->validateLandlordResponse($response, false);

        $notification = $this->transition($appointment, function (Appointment $locked, ViewingSlot $slot, Listing $listing) use ($landlord, $response): array {
            $this->authorizeLandlordForListing($landlord, $listing);
            $this->requireStatus($locked, 'PENDING');

            $locked->forceFill([
                'status' => 'ACCEPTED',
                'landlord_response' => $response,
                'responded_at' => now(),
            ])->save();

            return $this->notificationContext($locked, $listing, $slot);
        });

        $this->notify($notification, $notification['renter_id'], 'APPOINTMENT_ACCEPTED');
    }

    public function reject(Appointment $appointment, User $landlord, string $response): void
    {
        $this->validateLandlordResponse($response, true);

        $notification = $this->transition($appointment, function (Appointment $locked, ViewingSlot $slot, Listing $listing) use ($landlord, $response): array {
            $this->authorizeLandlordForListing($landlord, $listing);
            $this->requireStatus($locked, 'PENDING');

            $locked->forceFill([
                'status' => 'REJECTED',
                'landlord_response' => $response,
                'responded_at' => now(),
            ])->save();

            return $this->notificationContext($locked, $listing, $slot);
        });

        $this->notify($notification, $notification['renter_id'], 'APPOINTMENT_REJECTED');
    }

    public function cancel(Appointment $appointment, User $renter, ?string $reason): void
    {
        if ($reason !== null && mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['cancellation_reason' => __('validation.max.string', ['attribute' => __('ui.appointments.cancellation_reason'), 'max' => 1000])]);
        }

        $notification = $this->transition($appointment, function (Appointment $locked, ViewingSlot $slot, Listing $listing) use ($renter, $reason): array {
            $this->authorizeRenter($renter);

            if ((int) $locked->renter_id !== (int) $renter->id) {
                throw new AuthorizationException;
            }

            if (! in_array($locked->status, Appointment::ACTIVE_STATUSES, true)) {
                $this->throwStaleTransition();
            }

            if (CarbonImmutable::now(ViewingSlot::TIMEZONE)->greaterThanOrEqualTo($slot->startAtVietnam())) {
                throw ValidationException::withMessages(['appointment' => __('validation.appointment_started')]);
            }

            $locked->forceFill([
                'status' => 'CANCELLED',
                'cancelled_by' => $renter->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $this->notificationContext($locked, $listing, $slot);
        });

        $this->notify($notification, $notification['landlord_id'], 'APPOINTMENT_CANCELLED');
    }

    public function complete(Appointment $appointment, User $landlord): void
    {
        $this->transition($appointment, function (Appointment $locked, ViewingSlot $slot, Listing $listing) use ($landlord): array {
            $this->authorizeLandlordForListing($landlord, $listing);
            $this->requireStatus($locked, 'ACCEPTED');

            if (CarbonImmutable::now(ViewingSlot::TIMEZONE)->lessThan($slot->endAtVietnam())) {
                throw new ConflictHttpException(__('validation.appointment_not_complete'));
            }

            $locked->forceFill([
                'status' => 'COMPLETED',
                'completed_at' => now(),
            ])->save();

            return [];
        });
    }

    public function autoCancelOverdue(int $appointmentId): bool
    {
        $notification = $this->transitionById($appointmentId, function (Appointment $locked, ViewingSlot $slot, Listing $listing): ?array {
            if ($locked->status !== 'PENDING' || $slot->startAtVietnam()->greaterThan(CarbonImmutable::now(ViewingSlot::TIMEZONE))) {
                return null;
            }

            $locked->forceFill([
                'status' => 'AUTO_CANCELLED',
                'cancelled_by' => null,
                'cancelled_at' => now(),
                'cancellation_reason' => 'VIEWING_TIME_PASSED',
            ])->save();

            return $this->notificationContext($locked, $listing, $slot);
        });

        if ($notification === null) {
            return false;
        }

        $this->notify($notification, $notification['renter_id'], 'APPOINTMENT_AUTO_CANCELLED', 'overdue');
        $this->notify($notification, $notification['landlord_id'], 'APPOINTMENT_AUTO_CANCELLED', 'overdue');

        return true;
    }

    /**
     * Must be called inside the listing occupancy transaction while the listing row is locked.
     *
     * @return array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>
     */
    public function autoCancelFutureForRentedListing(Listing $lockedListing, User $landlord): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Rented-listing appointment cancellation requires an active transaction.');
        }

        if (
            ! $landlord->hasRole('LANDLORD')
            || (int) $lockedListing->landlord_id !== (int) $landlord->id
        ) {
            throw new AuthorizationException;
        }

        return $this->autoCancelFutureAppointments(
            new Collection([$lockedListing]),
            $landlord->id,
            'LISTING_RENTED',
        );
    }

    /**
     * Must be called inside the listing deletion transaction while the listing row is locked.
     *
     * @return array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>
     */
    public function autoCancelFutureForDeletedListing(Listing $lockedListing, User $landlord): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Deleted-listing appointment cancellation requires an active transaction.');
        }

        if (! $landlord->hasRole('LANDLORD') || (int) $lockedListing->landlord_id !== (int) $landlord->id) {
            throw new AuthorizationException;
        }

        return $this->autoCancelFutureAppointments(
            new Collection([$lockedListing]),
            $landlord->id,
            'LISTING_DELETED',
        );
    }

    /**
     * @param  Collection<int, Listing>  $lockedListings
     * @return array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>
     */
    public function autoCancelFutureForAdminAction(Collection $lockedListings, User $admin, string $reason): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Administrative appointment cancellation requires an active transaction.');
        }

        if (! $admin->hasAnyRole('ADMIN', 'SUPER_ADMIN')) {
            throw new AuthorizationException;
        }

        if (! in_array($reason, ['LISTING_SUSPENDED', 'LANDLORD_ACCOUNT_LOCKED'], true)) {
            throw new RuntimeException('The administrative appointment cancellation reason is invalid.');
        }

        return $this->autoCancelFutureAppointments($lockedListings, $admin->id, $reason);
    }

    /**
     * @param  array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>  $notifications
     */
    public function notifyRentedAppointments(array $notifications): void
    {
        foreach ($notifications as $notification) {
            $this->notify($notification['context'], $notification['renter_id'], 'APPOINTMENT_AUTO_CANCELLED', 'rented');
        }
    }

    /**
     * @param  array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>  $notifications
     */
    public function notifyAutoCancelledAppointments(array $notifications): void
    {
        foreach ($notifications as $notification) {
            $this->notify(
                $notification['context'],
                $notification['renter_id'],
                'APPOINTMENT_AUTO_CANCELLED',
                $notification['cancellation_reason'],
            );
        }
    }

    public function canCancel(Appointment $appointment): bool
    {
        return in_array($appointment->status, Appointment::ACTIVE_STATUSES, true)
            && CarbonImmutable::now(ViewingSlot::TIMEZONE)->lessThan($appointment->slot->startAtVietnam());
    }

    public function canComplete(Appointment $appointment): bool
    {
        return $appointment->status === 'ACCEPTED'
            && CarbonImmutable::now(ViewingSlot::TIMEZONE)->greaterThanOrEqualTo($appointment->slot->endAtVietnam());
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function bookingWindow(): array
    {
        $now = CarbonImmutable::now(ViewingSlot::TIMEZONE);

        return [$now->addHours(2), $now->addHours(720)];
    }

    private function ensureWithinBookingWindow(ViewingSlot $slot): void
    {
        [$earliest, $latest] = $this->bookingWindow();
        $start = $slot->startAtVietnam();

        if (! $start->lessThan($slot->endAtVietnam())) {
            throw ValidationException::withMessages(['slot' => __('validation.appointment_slot_unavailable')]);
        }

        if ($start->lessThan($earliest) || $start->greaterThan($latest)) {
            throw ValidationException::withMessages(['slot' => __('validation.appointment_outside_window')]);
        }
    }

    private function isPubliclyEligible(int $listingId): bool
    {
        return $this->listingSearchQuery->build()->whereKey($listingId)->exists();
    }

    private function authorizeRenter(User $renter): void
    {
        if (! $renter->hasRole('RENTER')) {
            throw new AuthorizationException;
        }
    }

    private function authorizeLandlordForListing(User $landlord, Listing $listing): void
    {
        if (
            ! $landlord->hasRole('LANDLORD')
            || (int) $listing->landlord_id !== (int) $landlord->id
        ) {
            throw new AuthorizationException;
        }
    }

    /** @param callable(Appointment, ViewingSlot, Listing): array<string, mixed>|null $operation */
    private function transition(Appointment $requested, callable $operation): ?array
    {
        return $this->transitionById($requested->id, $operation);
    }

    /** @param callable(Appointment, ViewingSlot, Listing): array<string, mixed>|null $operation */
    private function transitionById(int $appointmentId, callable $operation): ?array
    {
        $slotId = Appointment::query()->whereKey($appointmentId)->value('slot_id');

        if ($slotId === null) {
            abort(404);
        }

        $listingId = ViewingSlot::query()->whereKey($slotId)->value('listing_id');

        if ($listingId === null) {
            abort(404);
        }

        return DB::transaction(function () use ($appointmentId, $slotId, $listingId, $operation): ?array {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listingId);
            $slot = ViewingSlot::query()
                ->where('listing_id', $listing->id)
                ->whereKey($slotId)
                ->lockForUpdate()
                ->firstOrFail();
            $appointment = Appointment::query()
                ->where('slot_id', $slot->id)
                ->whereKey($appointmentId)
                ->lockForUpdate()
                ->firstOrFail();

            return $operation($appointment, $slot, $listing);
        }, 3);
    }

    private function requireStatus(Appointment $appointment, string $status): void
    {
        if ($appointment->status !== $status) {
            $this->throwStaleTransition();
        }
    }

    private function validateLandlordResponse(?string $response, bool $required): void
    {
        if (($required && ($response === null || trim($response) === '')) || ($response !== null && mb_strlen($response) > 1000)) {
            $message = $required && ($response === null || trim($response) === '')
                ? __('validation.required', ['attribute' => __('ui.appointments.landlord_response')])
                : __('validation.max.string', ['attribute' => __('ui.appointments.landlord_response'), 'max' => 1000]);

            throw ValidationException::withMessages(['landlord_response' => $message]);
        }
    }

    private function throwStaleTransition(): never
    {
        throw new ConflictHttpException(__('ui.appointments.stale_transition'));
    }

    /**
     * @param  Collection<int, Listing>  $lockedListings
     * @return array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>
     */
    private function autoCancelFutureAppointments(Collection $lockedListings, int $actorId, string $reason): array
    {
        $listings = $lockedListings->keyBy('id');
        $listingIds = $listings->modelKeys();

        if ($listingIds === []) {
            return [];
        }

        $localNow = CarbonImmutable::now(ViewingSlot::TIMEZONE);
        $slots = ViewingSlot::query()
            ->whereIn('listing_id', $listingIds)
            ->whereRaw('TIMESTAMP(viewing_date, start_time) > ?', [$localNow->format('Y-m-d H:i:s')])
            ->orderBy('listing_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'listing_id']);

        if ($slots->isEmpty()) {
            return [];
        }

        $appointments = Appointment::query()
            ->whereIn('slot_id', $slots->modelKeys())
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->orderBy('slot_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $appointments->load('slot');

        $cancelledAt = now();
        $notifications = [];

        foreach ($appointments as $appointment) {
            $listing = $listings->get((int) $appointment->slot->listing_id);

            if (! $listing) {
                throw new RuntimeException('A locked appointment listing could not be found.');
            }

            $appointment->forceFill([
                'status' => 'AUTO_CANCELLED',
                'cancelled_by' => $actorId,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => $reason,
            ])->save();

            $notifications[] = [
                'context' => $this->notificationContext($appointment, $listing, $appointment->slot),
                'renter_id' => (int) $appointment->renter_id,
                'cancellation_reason' => $reason,
            ];
        }

        return $notifications;
    }

    /** @return array{appointment_id: int, listing_id: int, listing_title: string, landlord_id: int, renter_id: int, viewing_at: string} */
    private function notificationContext(Appointment $appointment, Listing $listing, ?ViewingSlot $slot = null): array
    {
        $slot ??= $appointment->slot;

        return [
            'appointment_id' => (int) $appointment->id,
            'listing_id' => (int) $listing->id,
            'listing_title' => (string) $listing->title,
            'landlord_id' => (int) $listing->landlord_id,
            'renter_id' => (int) $appointment->renter_id,
            'viewing_at' => $slot->startAtVietnam()->format('d/m/Y H:i'),
        ];
    }

    /** @param array<string, mixed> $context */
    private function notify(array $context, int $recipientId, string $type, string $reason = ''): void
    {
        $messageKey = match ($type) {
            'APPOINTMENT_BOOKED' => 'appointment_booked_message',
            'APPOINTMENT_ACCEPTED' => 'appointment_accepted_message',
            'APPOINTMENT_REJECTED' => 'appointment_rejected_message',
            'APPOINTMENT_CANCELLED' => 'appointment_cancelled_message',
            default => match ($reason) {
                'rented' => 'appointment_auto_cancelled_rented_message',
                'LISTING_SUSPENDED' => 'appointment_auto_cancelled_suspended_message',
                'LANDLORD_ACCOUNT_LOCKED' => 'appointment_auto_cancelled_locked_message',
                'LISTING_DELETED' => 'appointment_auto_cancelled_deleted_message',
                default => 'appointment_auto_cancelled_overdue_message',
            },
        };

        $titleKey = match ($type) {
            'APPOINTMENT_BOOKED' => 'appointment_booked_title',
            'APPOINTMENT_ACCEPTED' => 'appointment_accepted_title',
            'APPOINTMENT_REJECTED' => 'appointment_rejected_title',
            'APPOINTMENT_CANCELLED' => 'appointment_cancelled_title',
            default => 'appointment_auto_cancelled_title',
        };

        try {
            $notification = AppNotification::query()->make([
                'user_id' => $recipientId,
                'notification_type' => $type,
                'title' => __("ui.notifications.{$titleKey}"),
                'message' => Str::limit(__("ui.notifications.{$messageKey}", [
                    'listing' => $context['listing_title'],
                    'viewing_at' => $context['viewing_at'],
                ]), 1000, ''),
                'entity_type' => 'appointment',
                'entity_id' => $context['appointment_id'],
                'read_at' => null,
                'created_at' => now(),
            ]);

            if (! $notification->save()) {
                throw new RuntimeException('The appointment notification was not persisted.');
            }
        } catch (Throwable) {
            try {
                Log::warning('Appointment notification could not be created.', [
                    'appointment_id' => $context['appointment_id'],
                    'notification_type' => $type,
                ]);
            } catch (Throwable) {
                // A committed appointment transition must remain successful.
            }
        }
    }
}
