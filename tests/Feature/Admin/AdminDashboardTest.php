<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\Listing;
use App\Models\Report;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Report\ReportFeatureTestCase;

class AdminDashboardTest extends ReportFeatureTestCase
{
    public function test_admin_dashboard_requires_admin_or_super_admin_capability(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');

        foreach ([['RENTER'], ['LANDLORD'], ['RENTER', 'LANDLORD']] as $roles) {
            $user = $this->userWithRoles($roles);
            $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
        }

        foreach ([['ADMIN'], ['SUPER_ADMIN'], ['ADMIN', 'LANDLORD'], ['SUPER_ADMIN', 'RENTER'], ['SUPER_ADMIN', 'LANDLORD']] as $roles) {
            $user = $this->userWithRoles($roles);
            $response = $this->actingAs($user)->get('/admin/dashboard')->assertOk()->assertSee(route('admin.dashboard'));

            if (! in_array('LANDLORD', $roles, true)) {
                $response->assertDontSee(route('landlord.dashboard'));
            }
        }
    }

    public function test_admin_user_metrics_include_all_accounts_and_count_additive_landlord_roles_once(): void
    {
        $this->userWithRoles(['ADMIN']);
        $this->userWithRoles(['SUPER_ADMIN']);
        $this->userWithRoles(['LANDLORD', 'RENTER']);
        $lockedLandlord = $this->userWithRoles(['LANDLORD']);
        $lockedLandlord->forceFill(['account_status' => 'LOCKED'])->save();

        $admin = User::query()->whereHas('roles', fn ($query) => $query->where('code', 'ADMIN'))->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('metrics.total_users', 4)
            ->assertViewHas('metrics.total_landlords', 2);
    }

    public function test_admin_dashboard_reports_listing_states_on_three_independent_axes(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $this->listing($landlord, 'Pending suspended available', [
            'moderation_status' => 'PENDING',
            'occupancy_status' => 'AVAILABLE',
            'visibility_status' => 'SUSPENDED',
        ]);
        $approved = $this->listing($landlord, 'Approved rented hidden', [
            'moderation_status' => 'PENDING',
            'occupancy_status' => 'RENTED',
            'visibility_status' => 'HIDDEN',
        ]);
        $this->setCurrentModeration($approved, 'APPROVED', '2026-02-01 09:00:00');
        $this->listing($landlord, 'Rejected available visible', [
            'moderation_status' => 'REJECTED',
            'occupancy_status' => 'AVAILABLE',
            'visibility_status' => 'VISIBLE',
        ]);
        $this->listing($landlord, 'Deleted', [
            'deleted_at' => now(),
            'moderation_status' => 'PENDING',
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('listingStates', [
                'total_listings' => 3,
                'moderation' => ['PENDING' => 1, 'APPROVED' => 1, 'REJECTED' => 1],
                'occupancy' => ['AVAILABLE' => 2, 'RENTED' => 1],
                'visibility' => ['VISIBLE' => 1, 'HIDDEN' => 1, 'SUSPENDED' => 1],
            ]);
    }

    public function test_todays_appointments_use_the_vietnam_calendar_date_and_approved_status_set(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-31 17:30:00', 'UTC'));
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $renter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($landlord, 'Today');
        $deletedListing = $this->listing($landlord, 'Deleted today', ['deleted_at' => now()]);
        $today = CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString();
        $yesterday = CarbonImmutable::parse($today, ViewingSlot::TIMEZONE)->subDay()->toDateString();
        $tomorrow = CarbonImmutable::parse($today, ViewingSlot::TIMEZONE)->addDay()->toDateString();

        foreach (['PENDING', 'ACCEPTED', 'COMPLETED', 'REJECTED', 'CANCELLED', 'AUTO_CANCELLED'] as $status) {
            $this->appointmentOnDate($listing, $renter, $today, $status);
        }
        $this->appointmentOnDate($deletedListing, $renter, $today, 'PENDING');
        $this->appointmentOnDate($listing, $renter, $yesterday, 'PENDING');
        $this->appointmentOnDate($listing, $renter, $tomorrow, 'ACCEPTED');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('metrics.today_appointments', 4);
    }

    public function test_monthly_listing_chart_has_twelve_utc_buckets_and_keeps_deleted_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-15 12:00:00', 'UTC'));
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);

        $before = $this->listing($landlord, 'Before window');
        $firstLive = $this->listing($landlord, 'First month live');
        $firstDeleted = $this->listing($landlord, 'First month deleted', ['deleted_at' => now()]);
        $currentMonth = $this->listing($landlord, 'Current month');
        $after = $this->listing($landlord, 'After window');

        $originalTimeZone = DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone;
        DB::statement("SET time_zone = '+00:00'");
        try {
            foreach ([
                [$before, '2025-05-31 23:59:59'],
                [$firstLive, '2025-06-01 00:00:00'],
                [$firstDeleted, '2025-06-30 23:59:59'],
                [$currentMonth, '2026-05-31 23:59:59'],
                [$after, '2026-06-01 00:00:00'],
            ] as [$listing, $createdAt]) {
                DB::table('listings')->where('id', $listing->id)->update(['created_at' => $createdAt]);
            }
        } finally {
            DB::statement('SET time_zone = ?', [$originalTimeZone]);
        }

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('monthlyListings', function (array $months): bool {
                return count($months) === 12
                    && $months[0]['month'] === '2025-06'
                    && $months[0]['label'] === '06/2025'
                    && $months[0]['count'] === 2
                    && $months[1]['month'] === '2025-07'
                    && $months[1]['count'] === 0
                    && $months[11]['month'] === '2026-05'
                    && $months[11]['count'] === 1;
            });
    }

    public function test_category_distribution_includes_hidden_categories_and_excludes_deleted_inventory(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $hiddenCategory = RoomCategory::query()->create(['name' => 'Phòng lưu trữ', 'is_active' => false]);

        $this->listing($landlord, 'Hidden category one', [
            'category_id' => $hiddenCategory->id,
            'visibility_status' => 'SUSPENDED',
            'expires_at' => now()->subDay(),
        ]);
        $this->listing($landlord, 'Hidden category two', [
            'category_id' => $hiddenCategory->id,
            'occupancy_status' => 'RENTED',
            'moderation_status' => 'REJECTED',
        ]);
        $this->listing($landlord, 'Other category');
        $this->listing($landlord, 'Deleted category listing', [
            'category_id' => $hiddenCategory->id,
            'deleted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('categoryDistribution', function (array $distribution) use ($hiddenCategory): bool {
                $hidden = collect($distribution['categories'])->firstWhere('category_id', $hiddenCategory->id);

                return $distribution['total_listings'] === 3
                    && $hidden['name'] === 'Phòng lưu trữ'
                    && $hidden['is_active'] === false
                    && $hidden['count'] === 2
                    && $hidden['percentage'] === 66.7;
            })
            ->assertSee('Phòng lưu trữ')
            ->assertSee(__('ui.dashboard.hidden_category'));
    }

    public function test_admin_dashboard_shows_recent_pending_lists_bounded_and_deterministic(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $landlord = $this->userWithRoles(['LANDLORD']);
        $renter = $this->userWithRoles(['RENTER']);
        $pendingListings = [];
        $tiedSubmittedAt = now()->subDay();

        foreach (range(1, 6) as $index) {
            $listing = $this->listing($landlord, "Pending {$index}", ['moderation_status' => 'PENDING']);
            DB::table('listing_moderations')->where('id', $listing->current_moderation_id)
                ->update(['submitted_at' => $index >= 5 ? $tiedSubmittedAt : now()->subDays(7 - $index)]);
            $pendingListings[] = $listing->fresh();
        }

        $deleted = $this->listing($landlord, 'Newest but deleted', ['deleted_at' => now(), 'moderation_status' => 'PENDING']);
        DB::table('listing_moderations')->where('id', $deleted->current_moderation_id)->update(['submitted_at' => now()->addDay()]);

        $historicalOnly = $this->listing($landlord, 'Historical pending only', ['moderation_status' => 'PENDING']);
        $this->setCurrentModeration($historicalOnly, 'APPROVED', now()->addDays(2)->toDateTimeString());

        $reports = [];
        $tiedReportedAt = now()->subDay();
        foreach (range(1, 6) as $index) {
            $target = $this->listing($landlord, "Report target {$index}", $index === 6 ? ['deleted_at' => now()] : []);
            $report = $this->report($renter, $target);
            DB::table('reports')->where('id', $report->id)->update([
                'created_at' => $index >= 5 ? $tiedReportedAt : now()->subDays(7 - $index),
            ]);
            $reports[] = $report->fresh();
        }
        $resolvedTarget = $this->listing($landlord, 'Resolved target');
        $this->report($renter, $resolvedTarget, ['status' => 'RESOLVED']);

        $response = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $recentListings = $response->viewData('recentPendingModeration');
        $recentReports = $response->viewData('recentPendingReports');

        $this->assertCount(5, $recentListings);
        $this->assertSame($pendingListings[5]->id, $recentListings->first()->id);
        $this->assertTrue($recentListings->every(fn (Listing $listing): bool => $listing->relationLoaded('currentModeration')));
        $this->assertCount(5, $recentReports);
        $this->assertSame($reports[5]->id, $recentReports->first()->id);
        $this->assertTrue($recentReports->every(fn (Report $report): bool => $report->relationLoaded('listing') && $report->relationLoaded('reason')));
        $response->assertViewHas('metrics.pending_reports', 6)
            ->assertDontSee('Newest but deleted')
            ->assertDontSee('Historical pending only')
            ->assertSee('Report target 6');
    }

    public function test_category_distribution_is_empty_when_there_are_no_non_deleted_listings(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('categoryDistribution', ['total_listings' => 0, 'categories' => []])
            ->assertSee(__('ui.dashboard.empty_categories'));
    }

    private function setCurrentModeration(Listing $listing, string $status, string $submittedAt): void
    {
        $moderation = $listing->moderations()->create([
            'version_no' => 2,
            'status' => $status,
            'reviewed_by' => $status === 'PENDING' ? null : $listing->landlord_id,
            'rejection_reason' => $status === 'REJECTED' ? 'Test reason' : null,
            'submitted_at' => $submittedAt,
            'reviewed_at' => $status === 'PENDING' ? null : $submittedAt,
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
    }

    private function appointmentOnDate(Listing $listing, User $renter, string $date, string $status): Appointment
    {
        $minute = ViewingSlot::query()->count() % 60;
        $slot = ViewingSlot::query()->create([
            'listing_id' => $listing->id,
            'viewing_date' => $date,
            'start_time' => sprintf('10:%02d:00', $minute),
            'end_time' => sprintf('11:%02d:00', $minute),
            'status' => 'OPEN',
        ]);

        return Appointment::query()->create([
            'slot_id' => $slot->id,
            'renter_id' => $renter->id,
            'status' => $status,
        ]);
    }
}
