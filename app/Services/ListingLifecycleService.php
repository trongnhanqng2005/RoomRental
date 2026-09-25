<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ListingLifecycleService
{
    public function __construct(private AppointmentService $appointmentService) {}

    public function canRenew(Listing $listing, User $landlord): bool
    {
        return $this->isRenewable($listing, $landlord, now());
    }

    private function isRenewable(Listing $listing, User $landlord, Carbon $now): bool
    {
        $expiry = $listing->effectiveExpiresAt();

        return (int) $listing->landlord_id === (int) $landlord->id
            && $landlord->hasRole('LANDLORD')
            && $landlord->account_status === 'ACTIVE'
            && $listing->deleted_at === null
            && $listing->occupancy_status === 'AVAILABLE'
            && $listing->visibility_status !== 'SUSPENDED'
            && $listing->currentModeration?->status === 'APPROVED'
            && $expiry !== null
            && $expiry->lessThanOrEqualTo($now);
    }

    public function renew(Listing $listing, User $landlord): void
    {
        DB::transaction(function () use ($listing, $landlord): void {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $this->authorizeOwner($lockedListing, $landlord);
            $now = now();
            $landlord->account_status = User::query()->whereKey($landlord->id)->value('account_status');

            if (! $this->isRenewable($lockedListing, $landlord, $now)) {
                throw new ConflictHttpException(__('ui.listings.lifecycle_stale'));
            }

            $lockedListing->expires_at = $now->copy()->addDays(30);
            $lockedListing->save();
        }, 3);
    }

    public function delete(Listing $listing, User $landlord): void
    {
        $notifications = DB::transaction(function () use ($listing, $landlord): array {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $this->authorizeOwner($lockedListing, $landlord);

            if ($lockedListing->deleted_at !== null) {
                throw new ConflictHttpException(__('ui.listings.lifecycle_stale'));
            }

            $notifications = $this->appointmentService->autoCancelFutureForDeletedListing($lockedListing, $landlord);

            $lockedListing->deleted_at = now();
            $lockedListing->save();

            return $notifications;
        }, 3);

        $this->appointmentService->notifyAutoCancelledAppointments($notifications);
    }

    public function unsuspend(Listing $listing, User $admin): void
    {
        if (! $admin->hasAnyRole('ADMIN', 'SUPER_ADMIN')) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($listing, $admin): void {
            $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($lockedListing->visibility_status !== 'SUSPENDED') {
                throw new ConflictHttpException(__('ui.listings.lifecycle_stale'));
            }

            $lockedListing->visibility_status = 'HIDDEN';
            $lockedListing->save();

            AuditLog::query()->create([
                'actor_user_id' => $admin->id,
                'action' => 'listing.unsuspended',
                'entity_type' => Listing::class,
                'entity_id' => $lockedListing->id,
                'created_at' => now(),
            ]);
        }, 3);
    }

    private function authorizeOwner(Listing $listing, User $landlord): void
    {
        if (! $landlord->hasRole('LANDLORD') || (int) $listing->landlord_id !== (int) $landlord->id) {
            throw new AuthorizationException;
        }
    }
}
