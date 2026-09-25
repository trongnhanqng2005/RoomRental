<?php

namespace Tests\Feature\Admin;

use App\Models\AppNotification;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Feature\Report\ReportFeatureTestCase;

class UserAccountLockTest extends ReportFeatureTestCase
{
    public function test_manual_lock_preserves_listing_axes_cancels_only_future_active_appointments_and_notifies_each_recipient(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, 'Tin sẽ bị ẩn do khóa tài khoản', [
            'visibility_status' => 'HIDDEN',
            'occupancy_status' => 'RENTED',
        ]);
        $admin = $this->userWithRoles(['ADMIN']);
        $futureRenter = $this->userWithRoles(['RENTER']);
        $startedRenter = $this->userWithRoles(['RENTER']);
        $terminalRenter = $this->userWithRoles(['RENTER']);
        $future = $this->appointment($listing, $futureRenter, 3, 'ACCEPTED');
        $started = $this->appointment($listing, $startedRenter, -1, 'PENDING');
        $terminal = $this->appointment($listing, $terminalRenter, 4, 'COMPLETED');

        $this->actingAs($admin)
            ->patch(route('admin.users.lock', $landlord))
            ->assertRedirect(route('admin.users.show', $landlord));

        $this->assertSame('LOCKED', $landlord->fresh()->account_status);
        $this->assertSame('HIDDEN', $listing->fresh()->visibility_status);
        $this->assertSame('RENTED', $listing->fresh()->occupancy_status);
        $this->assertSame('AUTO_CANCELLED', $future->fresh()->status);
        $this->assertSame($admin->id, $future->fresh()->cancelled_by);
        $this->assertSame('LANDLORD_ACCOUNT_LOCKED', $future->fresh()->cancellation_reason);
        $this->assertNotNull($future->fresh()->cancelled_at);
        $this->assertSame('PENDING', $started->fresh()->status);
        $this->assertSame('COMPLETED', $terminal->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $landlord->id, 'notification_type' => 'ACCOUNT_LOCKED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $futureRenter->id, 'notification_type' => 'APPOINTMENT_AUTO_CANCELLED']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $startedRenter->id, 'notification_type' => 'APPOINTMENT_AUTO_CANCELLED']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'user.locked', 'entity_id' => $landlord->id]);

        $this->get(route('public.listings.index'))->assertDontSee('Tin sẽ bị ẩn do khóa tài khoản');
    }

    public function test_repeated_lock_does_not_repeat_appointments_audit_or_notifications(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $renter = $this->userWithRoles(['RENTER']);
        $appointment = $this->appointment($listing, $renter);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)->patch(route('admin.users.lock', $landlord));
        $this->actingAs($admin)->patch(route('admin.users.lock', $landlord));

        $this->assertSame('AUTO_CANCELLED', $appointment->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'user.locked')->count());
        $this->assertSame(1, AppNotification::query()->where('notification_type', 'ACCOUNT_LOCKED')->count());
        $this->assertSame(1, AppNotification::query()->where('notification_type', 'APPOINTMENT_AUTO_CANCELLED')->count());
    }

    public function test_locked_landlord_listing_remains_hidden_from_public_eligibility_after_unlock_without_restoring_states(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, 'Tin vẫn ẩn sau mở khóa', ['visibility_status' => 'HIDDEN']);
        $admin = $this->userWithRoles(['ADMIN']);
        $this->actingAs($admin)->patch(route('admin.users.lock', $landlord));

        $this->actingAs($admin)->patch(route('admin.users.unlock', $landlord))->assertRedirect();

        $this->assertSame('ACTIVE', $landlord->fresh()->account_status);
        $this->assertSame('HIDDEN', $listing->fresh()->visibility_status);
        $this->assertDatabaseHas('notifications', ['user_id' => $landlord->id, 'notification_type' => 'ACCOUNT_UNLOCKED']);
        $this->get(route('public.listings.index'))->assertDontSee('Tin vẫn ẩn sau mở khóa');
    }

    public function test_target_notification_failure_does_not_rollback_lock_or_prevent_renter_notification(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $renter = $this->userWithRoles(['RENTER']);
        $appointment = $this->appointment($listing, $renter);
        $admin = $this->userWithRoles(['ADMIN']);
        AppNotification::creating(function (AppNotification $notification) use ($landlord): void {
            if ((int) $notification->user_id === (int) $landlord->id) {
                throw new RuntimeException('Forced account notification failure.');
            }
        });
        Log::spy();

        try {
            $this->actingAs($admin)
                ->patch(route('admin.users.lock', $landlord))
                ->assertRedirect();
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertSame('LOCKED', $landlord->fresh()->account_status);
        $this->assertSame('AUTO_CANCELLED', $appointment->fresh()->status);
        $this->assertDatabaseMissing('notifications', ['user_id' => $landlord->id, 'notification_type' => 'ACCOUNT_LOCKED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $renter->id, 'notification_type' => 'APPOINTMENT_AUTO_CANCELLED']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_report_enforcement_still_cancels_future_appointments_when_target_is_already_locked(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $reporter = $this->userWithRoles(['RENTER']);
        $renter = $this->userWithRoles(['RENTER']);
        $report = $this->report($reporter, $listing);
        $appointment = $this->appointment($listing, $renter);
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord->forceFill(['account_status' => 'LOCKED'])->save();

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'action_type' => 'LOCK_ACCOUNT',
                'reason' => 'Đã xác minh vi phạm.',
            ])
            ->assertRedirect();

        $this->assertSame('LOCKED', $landlord->fresh()->account_status);
        $this->assertSame('AUTO_CANCELLED', $appointment->fresh()->status);
        $this->assertSame('LANDLORD_ACCOUNT_LOCKED', $appointment->fresh()->cancellation_reason);
        $this->assertSame($admin->id, $appointment->fresh()->cancelled_by);
    }
}
