<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountLockService
{
    public function __construct(private AppointmentService $appointmentService) {}

    /**
     * The target user row must already be locked by the caller.
     *
     * @return array{changed: bool, appointment_notifications: array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>}
     */
    public function lock(User $target, User $actor, bool $cancelAppointmentsWhenAlreadyLocked = false): array
    {
        if (DB::transactionLevel() === 0 || ! $target->exists) {
            throw new RuntimeException('Account locking requires a locked target inside a transaction.');
        }

        if (
            ! $actor->hasAnyRole('ADMIN', 'SUPER_ADMIN')
            || (int) $target->id === (int) $actor->id
            || $target->hasRole('SUPER_ADMIN')
            || ($target->hasRole('ADMIN') && ! $actor->hasRole('SUPER_ADMIN'))
        ) {
            throw new AuthorizationException;
        }

        $changed = $target->account_status === 'ACTIVE';

        if (! $changed && ! $cancelAppointmentsWhenAlreadyLocked) {
            return ['changed' => false, 'appointment_notifications' => []];
        }

        if (! $changed && $target->account_status !== 'LOCKED') {
            throw new RuntimeException('The account status is not supported.');
        }

        if ($changed) {
            $target->forceFill(['account_status' => 'LOCKED'])->save();
        }

        $appointmentNotifications = [];

        if ($target->hasRole('LANDLORD')) {
            $listings = Listing::query()
                ->where('landlord_id', $target->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $appointmentNotifications = $this->appointmentService->autoCancelFutureForAdminAction(
                $listings,
                $actor,
                'LANDLORD_ACCOUNT_LOCKED',
            );
        }

        return ['changed' => $changed, 'appointment_notifications' => $appointmentNotifications];
    }
}
