<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\FeeType;
use App\Models\FeeUnit;
use App\Models\Listing;
use App\Models\ListingModeration;
use App\Models\Province;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Ward;
use App\Services\ListingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class ListingModerationTest extends TestCase
{
    use RefreshDatabase;

    private RoomCategory $category;

    private Amenity $amenity;

    private Ward $ward;

    private FeeType $feeType;

    private FeeUnit $feeUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->category = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true]);
        $this->amenity = Amenity::query()->create(['name' => 'Wi-Fi', 'is_active' => true]);
        $province = Province::query()->create(['code' => 'P01', 'name' => 'Hà Nội', 'is_active' => true]);
        $district = District::query()->create(['province_id' => $province->id, 'code' => 'D01', 'name' => 'Cầu Giấy', 'is_active' => true]);
        $this->ward = Ward::query()->create(['district_id' => $district->id, 'code' => 'W01', 'name' => 'Dịch Vọng', 'is_active' => true]);
        $this->feeType = FeeType::query()->create(['code' => 'ELECTRICITY', 'name' => 'Điện', 'is_active' => true]);
        $this->feeUnit = FeeUnit::query()->create(['code' => 'PER_KWH', 'name' => 'Theo kWh', 'is_active' => true]);
    }

    public function test_guest_cannot_access_moderation_queue_or_detail_or_actions(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;

        $this->get(route('admin.listing-moderations.index'))->assertRedirect('/login');
        $this->get(route('admin.listing-moderations.show', $listing))->assertRedirect('/login');
        $this->post(route('admin.listing-moderations.approve', [$listing, $moderation]))->assertRedirect('/login');
        $this->post(route('admin.listing-moderations.reject', [$listing, $moderation]), ['rejection_reason' => 'Không phù hợp'])
            ->assertRedirect('/login');

        $this->assertSame('PENDING', $moderation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertNotNull($admin);
    }

    public function test_renter_and_landlord_cannot_access_moderation_routes_or_actions(): void
    {
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;

        foreach (['RENTER', 'LANDLORD'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('admin.listing-moderations.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.listing-moderations.show', $listing))->assertForbidden();
            $this->actingAs($user)->post(route('admin.listing-moderations.approve', [$listing, $moderation]))->assertForbidden();
            $this->actingAs($user)->post(route('admin.listing-moderations.reject', [$listing, $moderation]), ['rejection_reason' => 'Không phù hợp'])
                ->assertForbidden();
        }

        $this->assertSame('PENDING', $moderation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_admin_and_super_admin_can_view_a_queue_of_only_current_pending_listings(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $pending = $this->createListing($this->userWithRole('LANDLORD'), 'Tin đang chờ duyệt');
        $approved = $this->createListing($this->userWithRole('LANDLORD'), 'Tin đã duyệt');
        $this->setCurrentModeration($approved, 'APPROVED', 2, $admin);
        $rejected = $this->createListing($this->userWithRole('LANDLORD'), 'Tin đã từ chối');
        $this->setCurrentModeration($rejected, 'REJECTED', 2, $admin, 'Thiếu thông tin');

        foreach (['ADMIN', 'SUPER_ADMIN'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.listing-moderations.index'))
                ->assertOk()
                ->assertSee('Tin đang chờ duyệt')
                ->assertSee('Phòng trọ')
                ->assertSee('Hà Nội')
                ->assertDontSee('Tin đã duyệt')
                ->assertDontSee('Tin đã từ chối');
        }

        $this->assertSame('PENDING', $pending->currentModeration->status);
    }

    public function test_moderation_queue_is_paginated(): void
    {
        $admin = $this->userWithRole('ADMIN');

        foreach (range(1, 11) as $index) {
            $this->createListing($this->userWithRole('LANDLORD'), "Tin chờ {$index}");
        }

        $this->actingAs($admin)
            ->get(route('admin.listing-moderations.index'))
            ->assertOk()
            ->assertViewHas('listings', fn ($listings): bool => $listings->count() === 10 && $listings->total() === 11);
    }

    public function test_admin_can_view_listing_content_landlord_status_axes_images_fees_and_moderation_history(): void
    {
        $landlord = $this->userWithRole('LANDLORD');
        $landlord->profile()->update([
            'full_name' => 'Nguyễn Chủ Trọ',
            'contact_address' => '12 Nguyễn Trãi, Hà Nội',
            'zalo_number' => '0900000000',
        ]);
        $listing = $this->createListing($landlord, 'Phòng đầy đủ nội thất', 'PENDING', 'RENTED', 'HIDDEN');
        $this->setCurrentModeration($listing, 'PENDING', 2);

        $this->actingAs($this->userWithRole('ADMIN'))
            ->get(route('admin.listing-moderations.show', $listing))
            ->assertOk()
            ->assertSee('Phòng đầy đủ nội thất')
            ->assertSee('Mô tả chi tiết căn phòng')
            ->assertSee('Phòng trọ')
            ->assertSee('3.500.000')
            ->assertSee('24,50')
            ->assertSee('Dịch Vọng')
            ->assertSee('Cầu Giấy')
            ->assertSee('Hà Nội')
            ->assertSee('12 Nguyễn Trãi')
            ->assertSee('Wi-Fi')
            ->assertSee('Điện')
            ->assertSee('Theo kWh')
            ->assertSee('Ảnh 1')
            ->assertSee('Nguyễn Chủ Trọ')
            ->assertSee('landlord@example.com')
            ->assertSee('0900000000')
            ->assertSee('Phiên bản 2')
            ->assertSee('Chờ duyệt')
            ->assertSee('Đã thuê')
            ->assertSee('Đang ẩn');
    }

    public function test_admin_can_approve_current_pending_moderation_without_changing_other_state_or_history(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'), 'Căn phòng cần duyệt', 'PENDING', 'RENTED', 'HIDDEN');
        $priorVersion = $listing->currentModeration->id;
        $this->setCurrentModeration($listing, 'PENDING', 2);
        $current = $listing->fresh()->currentModeration;
        $submittedAt = $current->submitted_at;

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$listing, $current]))
            ->assertRedirect(route('admin.listing-moderations.show', $listing));

        $listing->refresh();
        $current->refresh();
        $this->assertSame('APPROVED', $current->status);
        $this->assertSame($admin->id, $current->reviewed_by);
        $this->assertNotNull($current->reviewed_at);
        $this->assertSame(
            $current->reviewed_at->copy()->addDays(30)->toDateTimeString(),
            $listing->expires_at->toDateTimeString(),
        );
        $this->assertNull($current->rejection_reason);
        $this->assertSame($current->id, $listing->current_moderation_id);
        $this->assertSame('RENTED', $listing->occupancy_status);
        $this->assertSame('HIDDEN', $listing->visibility_status);
        $this->assertSame($submittedAt->toDateTimeString(), $current->submitted_at->toDateTimeString());
        $this->assertSame(2, $listing->moderations()->count());
        $this->assertDatabaseHas('listing_moderations', ['id' => $priorVersion, 'status' => 'PENDING']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'listing_moderation.approved',
            'entity_type' => ListingModeration::class,
            'entity_id' => $current->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $listing->landlord_id,
            'notification_type' => 'LISTING_APPROVED',
            'entity_type' => 'listing',
            'entity_id' => $listing->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.listing-moderations.show', $listing))
            ->assertOk()
            ->assertSee('Đã duyệt');
    }

    public function test_approval_after_critical_edit_starts_a_fresh_expiry_period(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $landlord = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($landlord);
        $originalExpiry = now()->addDays(4);
        $listing->expires_at = $originalExpiry;
        $listing->save();

        app(ListingService::class)->update($listing, $this->criticalUpdateData($listing));
        $pendingVersion = $listing->fresh()->currentModeration;
        $this->assertSame($originalExpiry->toDateTimeString(), $listing->fresh()->expires_at->toDateTimeString());

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$listing, $pendingVersion]))
            ->assertRedirect();

        $pendingVersion->refresh();
        $listing->refresh();
        $this->assertSame(
            $pendingVersion->reviewed_at->copy()->addDays(30)->toDateTimeString(),
            $listing->expires_at->toDateTimeString(),
        );
    }

    public function test_admin_can_reject_current_pending_moderation_with_required_reason(): void
    {
        $admin = $this->userWithRole('SUPER_ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'), 'Tin cần bổ sung', 'PENDING', 'AVAILABLE', 'VISIBLE');
        $moderation = $listing->currentModeration;

        $this->actingAs($admin)
            ->from(route('admin.listing-moderations.show', $listing))
            ->post(route('admin.listing-moderations.reject', [$listing, $moderation]), ['rejection_reason' => 'Vui lòng bổ sung ảnh khu vực bếp.'])
            ->assertRedirect(route('admin.listing-moderations.show', $listing));

        $listing->refresh();
        $moderation->refresh();
        $this->assertSame('REJECTED', $moderation->status);
        $this->assertSame('Vui lòng bổ sung ảnh khu vực bếp.', $moderation->rejection_reason);
        $this->assertSame($admin->id, $moderation->reviewed_by);
        $this->assertNotNull($moderation->reviewed_at);
        $this->assertSame($moderation->id, $listing->current_moderation_id);
        $this->assertSame('AVAILABLE', $listing->occupancy_status);
        $this->assertSame('VISIBLE', $listing->visibility_status);
        $this->assertSame(1, $listing->moderations()->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'listing_moderation.rejected',
            'entity_type' => ListingModeration::class,
            'entity_id' => $moderation->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $listing->landlord_id,
            'notification_type' => 'LISTING_REJECTED',
            'entity_type' => 'listing',
            'entity_id' => $listing->id,
        ]);
        $this->assertStringContainsString(
            'Vui lòng bổ sung ảnh khu vực bếp.',
            AppNotification::query()->where('entity_id', $listing->id)->value('message'),
        );
    }

    public function test_rejection_without_reason_fails_validation_and_does_not_review_the_listing(): void
    {
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;

        $this->actingAs($this->userWithRole('ADMIN'))
            ->from(route('admin.listing-moderations.show', $listing))
            ->post(route('admin.listing-moderations.reject', [$listing, $moderation]), ['rejection_reason' => ''])
            ->assertRedirect(route('admin.listing-moderations.show', $listing))
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('PENDING', $moderation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_approved_and_rejected_moderations_cannot_be_reviewed_again(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $this->setCurrentModeration($listing, 'APPROVED', 2, $admin);
        $approved = $listing->fresh()->currentModeration;
        $auditCount = AuditLog::query()->count();
        $notificationCount = AppNotification::query()->count();

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.reject', [$listing, $approved]), ['rejection_reason' => 'Thay đổi quyết định'])
            ->assertStatus(409);

        $rejectedListing = $this->createListing($this->userWithRole('LANDLORD', 'second-landlord@example.com'));
        $this->setCurrentModeration($rejectedListing, 'REJECTED', 2, $admin, 'Lý do cũ');
        $rejected = $rejectedListing->fresh()->currentModeration;

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$rejectedListing, $rejected]))
            ->assertStatus(409);

        $this->assertSame('APPROVED', $approved->fresh()->status);
        $this->assertSame('REJECTED', $rejected->fresh()->status);
        $this->assertSame('Lý do cũ', $rejected->fresh()->rejection_reason);
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame($notificationCount, AppNotification::query()->count());
    }

    public function test_stale_moderation_id_cannot_be_approved_or_rejected_after_current_pointer_changes(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $stale = $listing->currentModeration;
        app(ListingService::class)->update($listing, $this->criticalUpdateData($listing));
        $current = $listing->fresh()->currentModeration;
        $this->assertSame(2, $current->version_no);

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$listing, $stale]))
            ->assertStatus(409);
        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.reject', [$listing, $stale]), ['rejection_reason' => 'Nội dung cũ'])
            ->assertStatus(409);

        $this->assertSame('PENDING', $stale->fresh()->status);
        $this->assertSame('PENDING', $current->fresh()->status);
        $this->assertSame($current->id, $listing->fresh()->current_moderation_id);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_double_submission_cannot_create_conflicting_decisions_or_duplicate_audit_records(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$listing, $moderation]))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.reject', [$listing, $moderation]), ['rejection_reason' => 'Lần gửi trùng'])
            ->assertStatus(409);

        $this->assertSame('APPROVED', $moderation->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('entity_id', $moderation->id)->count());
        $this->assertSame(1, AppNotification::query()->where('entity_id', $listing->id)->count());
    }

    public function test_audit_failure_rolls_back_the_moderation_decision(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;
        $auditHookFired = false;
        AuditLog::creating(function (AuditLog $auditLog) use (&$auditHookFired): void {
            $auditHookFired = true;
            throw new RuntimeException('Forced audit failure.');
        });

        try {
            $response = $this->actingAs($admin)
                ->post(route('admin.listing-moderations.approve', [$listing, $moderation]));
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertTrue($auditHookFired);
        $response->assertStatus(500);
        $this->assertSame('PENDING', $moderation->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_notification_failure_after_commit_does_not_fail_or_undo_the_decision(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $listing = $this->createListing($this->userWithRole('LANDLORD'));
        $moderation = $listing->currentModeration;
        $decisionWasCommittedWhenNotificationStarted = false;
        $transactionLevelBeforeDecision = DB::transactionLevel();
        $transactionLevelWhenNotificationStarted = null;
        AppNotification::creating(function () use (&$decisionWasCommittedWhenNotificationStarted, &$transactionLevelWhenNotificationStarted, $moderation): void {
            $transactionLevelWhenNotificationStarted = DB::transactionLevel();
            $decisionWasCommittedWhenNotificationStarted = DB::table('listing_moderations')
                ->where('id', $moderation->id)
                ->where('status', 'APPROVED')
                ->exists();

            throw new RuntimeException('Forced notification failure.');
        });
        Log::shouldReceive('warning')->once()->with(
            'Moderation notification could not be created.',
            \Mockery::on(fn (array $context): bool => isset($context['listing_id'], $context['moderation_id'])
                && ! isset($context['exception_message'])),
        );

        try {
            $this->actingAs($admin)
                ->post(route('admin.listing-moderations.approve', [$listing, $moderation]))
                ->assertRedirect(route('admin.listing-moderations.show', $listing));
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertTrue($decisionWasCommittedWhenNotificationStarted);
        $this->assertSame($transactionLevelBeforeDecision, $transactionLevelWhenNotificationStarted);
        $this->assertSame('APPROVED', $moderation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $moderation->id, 'action' => 'listing_moderation.approved']);
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_a_moderation_from_another_listing_cannot_be_used_in_a_direct_action_url(): void
    {
        $admin = $this->userWithRole('ADMIN');
        $first = $this->createListing($this->userWithRole('LANDLORD'));
        $second = $this->createListing($this->userWithRole('LANDLORD', 'another-landlord@example.com'));

        $this->actingAs($admin)
            ->post(route('admin.listing-moderations.approve', [$first, $second->currentModeration]))
            ->assertNotFound();

        $this->assertSame('PENDING', $first->currentModeration->fresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    private function userWithRole(string $role, string $email = 'landlord@example.com'): User
    {
        if (User::query()->where('email', $email)->exists()) {
            $email = fake()->unique()->safeEmail();
        }

        $user = User::factory()->create(['email' => $email]);
        $user->profile()->create(['full_name' => 'Test Landlord', 'contact_address' => '12 Nguyễn Trãi, Hà Nội']);
        $user->roles()->attach(Role::query()->where('code', $role)->value('id'), ['assigned_at' => now()]);

        return $user;
    }

    private function createListing(
        User $landlord,
        string $title = 'Phòng gần trung tâm',
        string $moderationStatus = 'PENDING',
        string $occupancyStatus = 'AVAILABLE',
        string $visibilityStatus = 'VISIBLE',
    ): Listing {
        $listing = new Listing;
        $listing->forceFill([
            'landlord_id' => $landlord->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'description' => 'Mô tả chi tiết căn phòng',
            'monthly_rent' => '3500000.00',
            'deposit_amount' => '3500000.00',
            'area_m2' => '24.50',
            'max_occupants' => 2,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'gender_requirement' => 'ANY',
            'ward_id' => $this->ward->id,
            'street_address' => '12 Nguyễn Trãi',
            'latitude' => '21.0285110',
            'longitude' => '105.8048170',
            'occupancy_status' => $occupancyStatus,
            'visibility_status' => $visibilityStatus,
            'view_count' => 0,
        ])->save();

        $moderation = $listing->moderations()->create([
            'version_no' => 1,
            'status' => $moderationStatus,
            'submitted_at' => now()->subMinute(),
            'reviewed_by' => $moderationStatus === 'PENDING' ? null : $landlord->id,
            'reviewed_at' => $moderationStatus === 'PENDING' ? null : now(),
            'rejection_reason' => $moderationStatus === 'REJECTED' ? 'Lý do cũ' : null,
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
        $listing->amenities()->attach($this->amenity->id);
        $listing->fees()->create([
            'fee_type_id' => $this->feeType->id,
            'fee_unit_id' => $this->feeUnit->id,
            'amount' => '3500.00',
            'note' => 'Theo công tơ',
        ]);

        foreach (range(1, 3) as $order) {
            $listing->images()->create([
                'image_url' => "listings/{$listing->id}/room-{$order}.jpg",
                'is_cover' => $order === 1,
                'display_order' => $order - 1,
                'created_at' => now(),
            ]);
        }

        return $listing->fresh(['currentModeration', 'moderations']);
    }

    private function setCurrentModeration(
        Listing $listing,
        string $status,
        int $version,
        ?User $reviewer = null,
        ?string $rejectionReason = null,
    ): ListingModeration {
        $moderation = $listing->moderations()->create([
            'version_no' => $version,
            'status' => $status,
            'submitted_at' => now(),
            'reviewed_by' => $status === 'PENDING' ? null : $reviewer?->id,
            'reviewed_at' => $status === 'PENDING' ? null : now(),
            'rejection_reason' => $status === 'REJECTED' ? $rejectionReason : null,
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();

        return $moderation;
    }

    /**
     * @return array<string, mixed>
     */
    private function criticalUpdateData(Listing $listing): array
    {
        $listing->load(['images', 'amenities', 'fees']);

        return [
            'category_id' => $listing->category_id,
            'title' => 'Phiên bản mới từ chủ trọ',
            'description' => $listing->description,
            'monthly_rent' => $listing->monthly_rent,
            'deposit_amount' => $listing->deposit_amount,
            'area_m2' => $listing->area_m2,
            'max_occupants' => $listing->max_occupants,
            'bedroom_count' => $listing->bedroom_count,
            'bathroom_count' => $listing->bathroom_count,
            'gender_requirement' => $listing->gender_requirement,
            'ward_id' => $listing->ward_id,
            'street_address' => $listing->street_address,
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'amenity_ids' => $listing->amenities->modelKeys(),
            'fees' => $listing->fees->map(fn ($fee) => [
                'fee_type_id' => $fee->fee_type_id,
                'fee_unit_id' => $fee->fee_unit_id,
                'amount' => $fee->amount,
                'note' => $fee->note,
            ])->all(),
            'existing_images' => $listing->images->modelKeys(),
            'new_images' => [],
            'cover_selection' => 'existing:'.$listing->images->firstWhere('is_cover', true)->id,
        ];
    }
}
