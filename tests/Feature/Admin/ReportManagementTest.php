<?php

namespace Tests\Feature\Admin;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\EnforcementAction;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Feature\Report\ReportFeatureTestCase;

class ReportManagementTest extends ReportFeatureTestCase
{
    public function test_guest_and_non_admin_users_cannot_access_report_management(): void
    {
        $report = $this->report($this->userWithRoles(['RENTER']), $this->listing($this->userWithRoles(['LANDLORD'])));

        $this->get(route('admin.reports.index'))->assertRedirect('/login');
        $this->get(route('admin.reports.show', $report))->assertRedirect('/login');
        $this->post(route('admin.reports.dismiss', $report), ['resolution_reason' => 'Không đủ thông tin.'])->assertRedirect('/login');

        foreach ([['RENTER'], ['LANDLORD'], ['RENTER', 'LANDLORD']] as $roles) {
            $user = $this->userWithRoles($roles);
            $this->actingAs($user)->get(route('admin.reports.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.reports.show', $report))->assertForbidden();
            $this->actingAs($user)
                ->post(route('admin.reports.dismiss', $report), ['resolution_reason' => 'Không đủ thông tin.'])
                ->assertForbidden();
        }

        $this->assertSame('PENDING', $report->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_and_super_admin_can_view_pending_queue_and_report_detail(): void
    {
        $reporter = $this->userWithRoles(['RENTER'], 'Người báo cáo');
        $landlord = $this->userWithRoles(['LANDLORD'], 'Chủ tin đăng');
        $listing = $this->listing($landlord, 'Tin cần xem xét');
        $pending = $this->report($reporter, $listing);
        $dismissed = $this->report($this->userWithRoles(['RENTER']), $this->listing($landlord, 'Tin đã xử lý'), [
            'status' => 'DISMISSED',
            'handled_by' => $this->userWithRoles(['ADMIN'])->id,
            'handled_at' => now(),
            'resolution_reason' => 'Không đủ bằng chứng.',
        ]);

        foreach (['ADMIN', 'SUPER_ADMIN'] as $role) {
            $queue = $this->actingAs($this->userWithRoles([$role]))
                ->get(route('admin.reports.index'))
                ->assertOk()
                ->assertSee('Tin cần xem xét')
                ->assertDontSee('Tin đã xử lý');

            $this->assertSame(1, $queue->viewData('reports')->total());
            $queuedReport = $queue->viewData('reports')->first();
            $this->assertTrue($queuedReport->relationLoaded('reporter'));
            $this->assertTrue($queuedReport->reporter->relationLoaded('profile'));
            $this->assertTrue($queuedReport->relationLoaded('reason'));
            $this->assertTrue($queuedReport->relationLoaded('listing'));
            $this->assertTrue($queuedReport->listing->relationLoaded('landlord'));
            $this->assertTrue($queuedReport->listing->landlord->relationLoaded('profile'));
            $this->assertTrue($queuedReport->listing->relationLoaded('currentModeration'));

            $detail = $this->actingAs($this->userWithRoles([$role]))
                ->get(route('admin.reports.show', $pending))
                ->assertOk()
                ->assertSee('Tin cần xem xét')
                ->assertSee('Người báo cáo')
                ->assertSee('Chủ tin đăng')
                ->assertSee('Thông tin cần được kiểm tra.')
                ->assertSee('Sai địa chỉ')
                ->assertDontSee('password_hash')
                ->assertDontSee('failed_login_count')
                ->assertDontSee('Địa chỉ riêng tư');

            $reportView = $detail->viewData('report');
            $this->assertTrue($reportView->relationLoaded('enforcementActions'));
            $this->assertTrue($reportView->listing->relationLoaded('images'));
            $this->assertTrue($reportView->listing->relationLoaded('amenities'));
            $this->assertTrue($reportView->listing->relationLoaded('fees'));
            $this->assertTrue($reportView->listing->currentModeration->exists);
            $this->assertArrayNotHasKey('contact_address', $reportView->listing->landlord->profile->getAttributes());
        }

        $this->assertSame('DISMISSED', $dismissed->fresh()->status);
    }

    public function test_report_queue_supports_status_filters_and_is_paginated(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);

        foreach (range(1, 11) as $index) {
            $this->report(
                $this->userWithRoles(['RENTER']),
                $this->listing($landlord, "Báo cáo {$index}"),
            );
        }

        $admin = $this->userWithRoles(['ADMIN']);
        $firstPage = $this->actingAs($admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->viewData('reports');
        $this->assertSame(10, $firstPage->count());
        $this->assertSame(11, $firstPage->total());

        $terminal = $this->report($this->userWithRoles(['RENTER']), $this->listing($landlord, 'Đã giải quyết'), [
            'status' => 'RESOLVED',
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['status' => 'RESOLVED']))
            ->assertOk()
            ->assertSee('Đã giải quyết')
            ->assertDontSee('Báo cáo 1');

        $this->assertSame('RESOLVED', $terminal->fresh()->status);
    }

    public function test_dismiss_requires_reason_and_writes_report_and_audit_atomically_without_enforcement(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $report = $this->report($reporter, $listing);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->from(route('admin.reports.show', $report))
            ->post(route('admin.reports.dismiss', $report), ['resolution_reason' => ''])
            ->assertSessionHasErrors('resolution_reason');

        $this->assertSame('PENDING', $report->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('enforcement_actions', 0);
        $this->assertDatabaseCount('notifications', 0);

        $this->actingAs($admin)
            ->post(route('admin.reports.dismiss', $report), [
                'resolution_reason' => 'Không đủ bằng chứng để xác minh.',
                'handled_by' => $reporter->id,
                'status' => 'RESOLVED',
            ])
            ->assertRedirect(route('admin.reports.show', $report));

        $report->refresh();
        $this->assertSame('DISMISSED', $report->status);
        $this->assertSame($admin->id, $report->handled_by);
        $this->assertNotNull($report->handled_at);
        $this->assertSame('Không đủ bằng chứng để xác minh.', $report->resolution_reason);
        $this->assertDatabaseCount('enforcement_actions', 0);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'report.dismissed',
            'entity_type' => Report::class,
            'entity_id' => $report->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'notification_type' => 'REPORT_HANDLED',
            'entity_type' => 'report',
            'entity_id' => $report->id,
        ]);
        $this->assertSame('VISIBLE', $listing->fresh()->visibility_status);
        $this->assertSame('ACTIVE', User::query()->findOrFail($listing->landlord_id)->account_status);
    }

    public function test_warning_targets_the_listing_landlord_and_does_not_mutate_listing_or_account(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, 'Tin cảnh báo', [
            'occupancy_status' => 'RENTED',
            'visibility_status' => 'HIDDEN',
        ]);
        $report = $this->report($reporter, $listing);
        $admin = $this->userWithRoles(['ADMIN']);
        $moderationId = $listing->current_moderation_id;

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'action_type' => 'WARNING',
                'reason' => 'Đã xác nhận thông tin cần được chỉnh sửa.',
                'target_user_id' => $reporter->id,
                'target_listing_id' => 999999,
                'status' => 'DISMISSED',
            ])
            ->assertRedirect(route('admin.reports.show', $report));

        $report->refresh();
        $listing->refresh();
        $landlord->refresh();
        $this->assertSame('RESOLVED', $report->status);
        $this->assertSame($admin->id, $report->handled_by);
        $this->assertNotNull($report->handled_at);
        $this->assertSame('HIDDEN', $listing->visibility_status);
        $this->assertSame('RENTED', $listing->occupancy_status);
        $this->assertSame($moderationId, $listing->current_moderation_id);
        $this->assertSame('ACTIVE', $landlord->account_status);
        $this->assertDatabaseHas('enforcement_actions', [
            'report_id' => $report->id,
            'admin_id' => $admin->id,
            'action_type' => 'WARNING',
            'target_user_id' => $landlord->id,
            'target_listing_id' => null,
            'reason' => 'Đã xác nhận thông tin cần được chỉnh sửa.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report.resolved.warning',
            'entity_type' => Report::class,
            'entity_id' => $report->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'notification_type' => 'REPORT_HANDLED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $landlord->id, 'notification_type' => 'LANDLORD_WARNING']);
        $this->assertSame(1, $report->enforcementActions()->count());
    }

    public function test_suspend_listing_cancels_only_future_active_appointments_and_preserves_other_axes(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, 'Tin bị tạm ngưng', ['occupancy_status' => 'RENTED']);
        $report = $this->report($reporter, $listing);
        $admin = $this->userWithRoles(['SUPER_ADMIN']);
        $pendingRenter = $this->userWithRoles(['RENTER']);
        $acceptedRenter = $this->userWithRoles(['RENTER']);
        $terminalRenter = $this->userWithRoles(['RENTER']);
        $pastRenter = $this->userWithRoles(['RENTER']);
        $pending = $this->appointment($listing, $pendingRenter, 3);
        $accepted = $this->appointment($listing, $acceptedRenter, 4, 'ACCEPTED');
        $rejected = $this->appointment($listing, $terminalRenter, 5, 'REJECTED');
        $past = $this->appointment($listing, $pastRenter, -1);
        $moderationId = $listing->current_moderation_id;

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'action_type' => 'SUSPEND_LISTING',
                'reason' => 'Tin vi phạm quy định hiển thị.',
                'target_listing_id' => 999999,
            ])
            ->assertRedirect(route('admin.reports.show', $report));

        $listing->refresh();
        $report->refresh();
        $this->assertSame('SUSPENDED', $listing->visibility_status);
        $this->assertSame('RENTED', $listing->occupancy_status);
        $this->assertSame($moderationId, $listing->current_moderation_id);
        $this->assertSame('RESOLVED', $report->status);

        foreach ([$pending, $accepted] as $cancelled) {
            $cancelled->refresh();
            $this->assertSame('AUTO_CANCELLED', $cancelled->status);
            $this->assertSame($admin->id, $cancelled->cancelled_by);
            $this->assertSame('LISTING_SUSPENDED', $cancelled->cancellation_reason);
            $this->assertNotNull($cancelled->cancelled_at);
        }
        $this->assertSame('REJECTED', $rejected->fresh()->status);
        $this->assertSame('PENDING', $past->fresh()->status);

        $this->assertDatabaseHas('enforcement_actions', [
            'report_id' => $report->id,
            'action_type' => 'SUSPEND_LISTING',
            'target_listing_id' => $listing->id,
            'target_user_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.resolved.suspend_listing', 'entity_id' => $report->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'notification_type' => 'REPORT_HANDLED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $landlord->id, 'notification_type' => 'LISTING_SUSPENDED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $pendingRenter->id, 'entity_id' => $pending->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $acceptedRenter->id, 'entity_id' => $accepted->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $terminalRenter->id, 'entity_id' => $rejected->id]);
    }

    public function test_lock_account_cancels_future_appointments_across_all_listings_without_changing_visibility(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $reportedListing = $this->listing($landlord, 'Tin được báo cáo', ['visibility_status' => 'HIDDEN']);
        $otherListing = $this->listing($landlord, 'Tin khác', ['visibility_status' => 'VISIBLE']);
        $report = $this->report($reporter, $reportedListing);
        $admin = $this->userWithRoles(['ADMIN']);
        $renterOne = $this->userWithRoles(['RENTER']);
        $renterTwo = $this->userWithRoles(['RENTER']);
        $terminalRenter = $this->userWithRoles(['RENTER']);
        $first = $this->appointment($reportedListing, $renterOne, 3);
        $second = $this->appointment($otherListing, $renterTwo, 4, 'ACCEPTED');
        $terminal = $this->appointment($otherListing, $terminalRenter, 5, 'COMPLETED');

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), [
                'action_type' => 'LOCK_ACCOUNT',
                'reason' => 'Đã xác minh vi phạm nghiêm trọng.',
                'target_user_id' => $reporter->id,
            ])
            ->assertRedirect(route('admin.reports.show', $report));

        $this->assertSame('LOCKED', $landlord->fresh()->account_status);
        $this->assertSame('HIDDEN', $reportedListing->fresh()->visibility_status);
        $this->assertSame('VISIBLE', $otherListing->fresh()->visibility_status);
        $this->assertSame('AUTO_CANCELLED', $first->fresh()->status);
        $this->assertSame('AUTO_CANCELLED', $second->fresh()->status);
        $this->assertSame('COMPLETED', $terminal->fresh()->status);

        foreach ([$first, $second] as $cancelled) {
            $cancelled->refresh();
            $this->assertSame($admin->id, $cancelled->cancelled_by);
            $this->assertSame('LANDLORD_ACCOUNT_LOCKED', $cancelled->cancellation_reason);
            $this->assertNotNull($cancelled->cancelled_at);
        }

        $this->assertDatabaseHas('enforcement_actions', [
            'report_id' => $report->id,
            'action_type' => 'LOCK_ACCOUNT',
            'target_user_id' => $landlord->id,
            'target_listing_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.resolved.lock_account', 'entity_id' => $report->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'notification_type' => 'REPORT_HANDLED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $landlord->id, 'notification_type' => 'ACCOUNT_LOCKED']);
        $this->assertDatabaseHas('notifications', ['user_id' => $renterOne->id, 'entity_id' => $first->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $renterTwo->id, 'entity_id' => $second->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $terminalRenter->id, 'entity_id' => $terminal->id]);
    }

    public function test_report_enforcement_cannot_lock_an_admin_or_super_admin_landlord(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $ordinaryAdmin = $this->userWithRoles(['ADMIN', 'LANDLORD']);
        $superAdmin = $this->userWithRoles(['SUPER_ADMIN', 'LANDLORD']);
        $reviewer = $this->userWithRoles(['ADMIN']);

        foreach ([$ordinaryAdmin, $superAdmin] as $target) {
            $listing = $this->listing($target);
            $report = $this->report($reporter, $listing);

            $this->actingAs($reviewer)
                ->from(route('admin.reports.show', $report))
                ->post(route('admin.reports.resolve', $report), [
                    'action_type' => 'LOCK_ACCOUNT',
                    'reason' => 'Đã xác minh.',
                ])
                ->assertSessionHasErrors('action_type');

            $this->assertSame('PENDING', $report->fresh()->status);
            $this->assertSame('ACTIVE', $target->fresh()->account_status);
        }

        $warningListing = $this->listing($ordinaryAdmin, 'Tin admin vẫn có thể cảnh báo');
        $warningReport = $this->report($reporter, $warningListing);
        $this->actingAs($reviewer)
            ->post(route('admin.reports.resolve', $warningReport), [
                'action_type' => 'WARNING',
                'reason' => 'Cần điều chỉnh nội dung.',
            ])
            ->assertRedirect();
        $this->assertSame('RESOLVED', $warningReport->fresh()->status);

        $suspendListing = $this->listing($superAdmin, 'Tin super admin vẫn có thể tạm ngưng');
        $suspendReport = $this->report($reporter, $suspendListing);
        $this->actingAs($reviewer)
            ->post(route('admin.reports.resolve', $suspendReport), [
                'action_type' => 'SUSPEND_LISTING',
                'reason' => 'Tin đăng vi phạm.',
            ])
            ->assertRedirect();
        $this->assertSame('SUSPENDED', $suspendListing->fresh()->visibility_status);
    }

    public function test_terminal_reports_cannot_be_handled_again_or_create_duplicate_side_effects(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $report = $this->report($reporter, $this->listing($this->userWithRoles(['LANDLORD'])));
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), ['action_type' => 'WARNING', 'reason' => 'Cảnh báo.'])
            ->assertRedirect();
        $counts = [
            EnforcementAction::query()->count(),
            AuditLog::query()->count(),
            AppNotification::query()->count(),
        ];

        $this->actingAs($admin)
            ->post(route('admin.reports.dismiss', $report), ['resolution_reason' => 'Gửi lại.'])
            ->assertStatus(409);
        $this->actingAs($admin)
            ->post(route('admin.reports.resolve', $report), ['action_type' => 'WARNING', 'reason' => 'Gửi lại.'])
            ->assertStatus(409);

        $this->assertSame('RESOLVED', $report->fresh()->status);
        $this->assertSame($counts, [
            EnforcementAction::query()->count(),
            AuditLog::query()->count(),
            AppNotification::query()->count(),
        ]);
    }

    public function test_audit_failure_rolls_back_suspension_appointment_cancellations_and_report(): void
    {
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $report = $this->report($this->userWithRoles(['RENTER']), $listing);
        $appointment = $this->appointment($listing, $this->userWithRoles(['RENTER']));
        $fired = false;
        AuditLog::creating(function () use (&$fired): void {
            $fired = true;
            throw new RuntimeException('Forced audit failure.');
        });

        try {
            $response = $this->actingAs($this->userWithRoles(['ADMIN']))
                ->post(route('admin.reports.resolve', $report), [
                    'action_type' => 'SUSPEND_LISTING',
                    'reason' => 'Vi phạm.',
                ]);
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertTrue($fired);
        $response->assertStatus(500);
        $this->assertSame('PENDING', $report->fresh()->status);
        $this->assertSame('VISIBLE', $listing->fresh()->visibility_status);
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertDatabaseCount('enforcement_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_audit_failure_rolls_back_account_lock_and_all_listing_appointment_cancellations(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $reportedListing = $this->listing($landlord, 'Tin bị báo cáo', ['visibility_status' => 'HIDDEN']);
        $otherListing = $this->listing($landlord, 'Tin thứ hai', ['visibility_status' => 'VISIBLE']);
        $report = $this->report($this->userWithRoles(['RENTER']), $reportedListing);
        $appointment = $this->appointment($otherListing, $this->userWithRoles(['RENTER']));
        $failureFired = false;
        AuditLog::creating(function (AuditLog $auditLog) use (&$failureFired): void {
            if ($auditLog->action === 'report.resolved.lock_account') {
                $failureFired = true;
                throw new RuntimeException('Forced account-lock audit failure.');
            }
        });

        try {
            $response = $this->actingAs($this->userWithRoles(['ADMIN']))
                ->post(route('admin.reports.resolve', $report), [
                    'action_type' => 'LOCK_ACCOUNT',
                    'reason' => 'Đã xác minh vi phạm.',
                ]);
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertTrue($failureFired);
        $response->assertStatus(500);
        $this->assertSame('PENDING', $report->fresh()->status);
        $this->assertSame('ACTIVE', $landlord->fresh()->account_status);
        $this->assertSame('HIDDEN', $reportedListing->fresh()->visibility_status);
        $this->assertSame('VISIBLE', $otherListing->fresh()->visibility_status);
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertDatabaseCount('enforcement_actions', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_failure_does_not_rollback_decision_or_prevent_remaining_recipients(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $report = $this->report($reporter, $this->listing($landlord));
        $failedNotification = false;
        $transactionLevelBeforeDecision = DB::transactionLevel();
        $transactionLevelAtNotification = null;
        $decisionWasCommittedAtNotification = false;
        AppNotification::creating(function (AppNotification $notification) use (
            $reporter,
            $report,
            &$failedNotification,
            &$transactionLevelAtNotification,
            &$decisionWasCommittedAtNotification,
        ): void {
            $transactionLevelAtNotification ??= DB::transactionLevel();
            $decisionWasCommittedAtNotification = DB::table('reports')
                ->where('id', $report->id)
                ->where('status', 'RESOLVED')
                ->exists();

            if ((int) $notification->user_id === (int) $reporter->id) {
                $failedNotification = true;
                throw new RuntimeException('Forced reporter notification failure.');
            }
        });
        Log::shouldReceive('warning')->once()->with(
            'Report notification could not be created.',
            \Mockery::on(fn (array $context): bool => isset($context['report_id'], $context['notification_type'])
                && ! isset($context['exception_message'])),
        );

        try {
            $this->actingAs($this->userWithRoles(['ADMIN']))
                ->post(route('admin.reports.resolve', $report), [
                    'action_type' => 'WARNING',
                    'reason' => 'Cần nhắc nhở chủ tin.',
                ])
                ->assertRedirect();
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertTrue($failedNotification);
        $this->assertSame($transactionLevelBeforeDecision, $transactionLevelAtNotification);
        $this->assertTrue($decisionWasCommittedAtNotification);
        $this->assertSame('RESOLVED', $report->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $landlord->id,
            'notification_type' => 'LANDLORD_WARNING',
            'entity_id' => $report->id,
        ]);
    }

    public function test_notifications_are_attempted_after_commit_and_failure_does_not_block_later_renters(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $report = $this->report($reporter, $listing);
        $renter = $this->userWithRoles(['RENTER']);
        $appointment = $this->appointment($listing, $renter);
        $transactionLevelBeforeDecision = DB::transactionLevel();
        $transactionLevelAtNotification = null;
        $decisionWasCommittedAtNotification = false;
        AppNotification::creating(function (AppNotification $notification) use ($landlord, $report, &$transactionLevelAtNotification, &$decisionWasCommittedAtNotification): void {
            $transactionLevelAtNotification ??= DB::transactionLevel();
            $decisionWasCommittedAtNotification = DB::table('reports')
                ->where('id', $report->id)
                ->where('status', 'RESOLVED')
                ->exists();

            if ((int) $notification->user_id === (int) $landlord->id && $notification->notification_type === 'LISTING_SUSPENDED') {
                throw new RuntimeException('Forced landlord notification failure.');
            }
        });
        Log::shouldReceive('warning')->once()->with(
            'Report notification could not be created.',
            \Mockery::on(fn (array $context): bool => $context['notification_type'] === 'LISTING_SUSPENDED'),
        );

        try {
            $this->actingAs($this->userWithRoles(['ADMIN']))
                ->post(route('admin.reports.resolve', $report), [
                    'action_type' => 'SUSPEND_LISTING',
                    'reason' => 'Tin vi phạm.',
                ])
                ->assertRedirect();
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertSame($transactionLevelBeforeDecision, $transactionLevelAtNotification);
        $this->assertTrue($decisionWasCommittedAtNotification);
        $this->assertSame('RESOLVED', $report->fresh()->status);
        $this->assertSame('AUTO_CANCELLED', $appointment->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'notification_type' => 'REPORT_HANDLED',
            'entity_id' => $report->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $renter->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $appointment->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $landlord->id,
            'notification_type' => 'LISTING_SUSPENDED',
            'entity_id' => $listing->id,
        ]);
    }
}
