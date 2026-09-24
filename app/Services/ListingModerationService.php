<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingModeration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ListingModerationService
{
    public function approve(Listing $listing, ListingModeration $moderation, User $reviewer): void
    {
        $this->decide($listing, $moderation, $reviewer, 'APPROVED', null);
    }

    public function reject(Listing $listing, ListingModeration $moderation, User $reviewer, string $reason): void
    {
        $this->decide($listing, $moderation, $reviewer, 'REJECTED', $reason);
    }

    private function decide(
        Listing $listing,
        ListingModeration $requestedModeration,
        User $reviewer,
        string $status,
        ?string $reason,
    ): void {
        $notification = DB::transaction(function () use ($listing, $requestedModeration, $reviewer, $status, $reason): array {
            $lockedListing = Listing::query()
                ->lockForUpdate()
                ->findOrFail($listing->id);

            $lockedModeration = ListingModeration::query()
                ->where('listing_id', $lockedListing->id)
                ->whereKey($requestedModeration->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedListing->current_moderation_id !== $lockedModeration->id
                || $lockedModeration->status !== 'PENDING'
            ) {
                throw new ConflictHttpException(__('ui.admin_moderation.stale_decision'));
            }

            $reviewedAt = now();
            $updated = ListingModeration::query()
                ->whereKey($lockedModeration->id)
                ->where('listing_id', $lockedListing->id)
                ->where('status', 'PENDING')
                ->update([
                    'status' => $status,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => $reviewedAt,
                    'rejection_reason' => $status === 'REJECTED' ? $reason : null,
                ]);

            if ($updated !== 1) {
                throw new ConflictHttpException(__('ui.admin_moderation.stale_decision'));
            }

            AuditLog::query()->create([
                'actor_user_id' => $reviewer->id,
                'action' => $status === 'APPROVED'
                    ? 'listing_moderation.approved'
                    : 'listing_moderation.rejected',
                'entity_type' => ListingModeration::class,
                'entity_id' => $lockedModeration->id,
                'created_at' => $reviewedAt,
            ]);

            return [
                'listing_id' => $lockedListing->id,
                'landlord_id' => $lockedListing->landlord_id,
                'moderation_id' => $lockedModeration->id,
                'listing_title' => $lockedListing->title,
                'status' => $status,
                'reason' => $reason,
            ];
        });

        $this->createNotificationAfterCommit($notification);
    }

    /**
     * @param  array{listing_id: int, landlord_id: int, moderation_id: int, listing_title: string, status: string, reason: ?string}  $decision
     */
    private function createNotificationAfterCommit(array $decision): void
    {
        $approved = $decision['status'] === 'APPROVED';
        $type = $approved ? 'LISTING_APPROVED' : 'LISTING_REJECTED';

        try {
            $title = $approved
                ? __('ui.notifications.listing_approved_title')
                : __('ui.notifications.listing_rejected_title');
            $message = $approved
                ? __('ui.notifications.listing_approved_message', ['listing' => $decision['listing_title']])
                : __('ui.notifications.listing_rejected_message', [
                    'listing' => $decision['listing_title'],
                    'reason' => $decision['reason'],
                ]);

            $appNotification = AppNotification::query()->make([
                'user_id' => $decision['landlord_id'],
                'notification_type' => $type,
                'title' => $title,
                'message' => Str::limit($message, 1000, ''),
                'entity_type' => 'listing',
                'entity_id' => $decision['listing_id'],
                'read_at' => null,
                'created_at' => now(),
            ]);

            if (! $appNotification->save()) {
                throw new RuntimeException('The moderation notification was not persisted.');
            }
        } catch (Throwable) {
            try {
                Log::warning('Moderation notification could not be created.', [
                    'listing_id' => $decision['listing_id'],
                    'moderation_id' => $decision['moderation_id'],
                    'notification_type' => $type,
                ]);
            } catch (Throwable) {
                // The already-committed moderation decision must remain successful.
            }
        }
    }
}
