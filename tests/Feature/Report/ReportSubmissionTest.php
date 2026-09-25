<?php

namespace Tests\Feature\Report;

use App\Models\Report;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PDOException;

class ReportSubmissionTest extends ReportFeatureTestCase
{
    public function test_public_detail_shows_login_affordance_to_guests_and_report_form_to_authenticated_non_owners(): void
    {
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));

        $guestResponse = $this->get(route('public.listings.show', $listing));
        $guestResponse->assertOk();
        $this->assertFalse($guestResponse->viewData('canReport'));
        $guestResponse
            ->assertSee(__('ui.reports.login_to_report'))
            ->assertDontSee('name="reason_id"', false);

        $reporter = $this->userWithRoles(['LANDLORD']);
        $response = $this->actingAs($reporter)->get(route('public.listings.show', $listing));

        $response->assertOk()
            ->assertSee(__('ui.reports.submit'))
            ->assertSee(__('ui.reports.reason'));
        $this->assertSame(5, $response->viewData('reportReasons')->count());
    }

    public function test_public_detail_hides_self_report_action_and_handles_empty_active_reason_catalog(): void
    {
        $owner = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($owner);

        $ownerResponse = $this->actingAs($owner)->get(route('public.listings.show', $listing));
        $ownerResponse->assertOk();
        $this->assertFalse($ownerResponse->viewData('canReport'));
        $ownerResponse
            ->assertDontSee('name="reason_id"', false)
            ->assertDontSee(__('ui.reports.login_to_report'));

        DB::table('report_reasons')->update(['is_active' => false]);
        $this->actingAs($this->userWithRoles(['RENTER']))
            ->get(route('public.listings.show', $listing))
            ->assertOk()
            ->assertSee(__('ui.reports.no_reasons'))
            ->assertDontSee('name="reason_id"', false);
    }

    public function test_guest_cannot_submit_a_report(): void
    {
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));

        $this->post(route('reports.store', $listing), $this->reportPayload())
            ->assertRedirect('/login');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_authenticated_users_with_different_role_sets_can_report_another_users_listing(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);

        foreach ([['RENTER'], ['LANDLORD'], ['ADMIN'], ['SUPER_ADMIN'], ['RENTER', 'LANDLORD']] as $roles) {
            $reporter = $this->userWithRoles($roles);

            $this->actingAs($reporter)
                ->post(route('reports.store', $listing), $this->reportPayload())
                ->assertRedirect(route('public.listings.show', $listing));

            $this->assertDatabaseHas('reports', [
                'reporter_id' => $reporter->id,
                'listing_id' => $listing->id,
                'reason_id' => $this->activeReasonId(),
                'status' => 'PENDING',
            ]);
        }

        $this->assertDatabaseCount('reports', 5);
    }

    public function test_reporter_cannot_report_their_own_listing(): void
    {
        $owner = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($owner);

        $this->actingAs($owner)
            ->post(route('reports.store', $listing), $this->reportPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_non_deleted_listings_can_be_reported_regardless_of_public_state(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listings = [
            $this->listing($landlord, 'Tin đang ẩn', ['visibility_status' => 'HIDDEN']),
            $this->listing($landlord, 'Tin tạm ngưng', ['visibility_status' => 'SUSPENDED']),
            $this->listing($landlord, 'Tin đã thuê', ['occupancy_status' => 'RENTED']),
            $this->listing($landlord, 'Tin chờ duyệt', ['moderation_status' => 'PENDING']),
            $this->listing($landlord, 'Tin bị từ chối', ['moderation_status' => 'REJECTED']),
            $this->listing($landlord, 'Tin hết hạn', ['expires_at' => now()->subDay()]),
        ];

        foreach ($listings as $listing) {
            $this->actingAs($this->userWithRoles(['RENTER']))
                ->post(route('reports.store', $listing), $this->reportPayload())
                ->assertRedirect(route('public.listings.show', $listing));
        }

        $this->assertDatabaseCount('reports', count($listings));
        $this->assertSame(
            count($listings),
            DB::table('reports')->where('status', 'PENDING')->count(),
        );
    }

    public function test_logically_deleted_listing_cannot_receive_a_new_report(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']), 'Tin đã xóa', ['deleted_at' => now()]);

        $this->actingAs($reporter)
            ->post(route('reports.store', $listing), $this->reportPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_inactive_or_invalid_report_reason_is_rejected(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $inactiveReason = DB::table('report_reasons')->insertGetId([
            'code' => 'INACTIVE_TEST_REASON',
            'name' => 'Lý do không hoạt động',
            'is_active' => false,
        ]);

        $this->actingAs($reporter)
            ->from(route('public.listings.show', $listing))
            ->post(route('reports.store', $listing), ['reason_id' => $inactiveReason])
            ->assertRedirect(route('public.listings.show', $listing))
            ->assertSessionHasErrors('reason_id');

        $this->actingAs($reporter)
            ->from(route('public.listings.show', $listing))
            ->post(route('reports.store', $listing), ['reason_id' => 999999])
            ->assertRedirect(route('public.listings.show', $listing))
            ->assertSessionHasErrors('reason_id');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_reporter_id_and_other_protected_fields_cannot_be_spoofed(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $otherUser = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($otherUser);

        $this->actingAs($reporter)
            ->post(route('reports.store', $listing), array_merge($this->reportPayload(), [
                'reporter_id' => $otherUser->id,
                'handled_by' => $otherUser->id,
                'target_user_id' => $otherUser->id,
                'target_listing_id' => $listing->id,
                'status' => 'RESOLVED',
            ]))
            ->assertRedirect(route('public.listings.show', $listing));

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'listing_id' => $listing->id,
            'status' => 'PENDING',
            'handled_by' => null,
        ]);
        $this->assertDatabaseCount('enforcement_actions', 0);
    }

    public function test_reporter_can_submit_again_after_the_prior_report_is_terminal(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $admin = $this->userWithRoles(['ADMIN']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));

        $this->actingAs($reporter)->post(route('reports.store', $listing), $this->reportPayload())->assertRedirect();
        DB::table('reports')->where('reporter_id', $reporter->id)->where('listing_id', $listing->id)->update([
            'status' => 'DISMISSED',
            'handled_by' => $admin->id,
            'handled_at' => now(),
            'resolution_reason' => 'Không đủ thông tin để xác minh.',
            'updated_at' => now(),
        ]);

        $this->actingAs($reporter)
            ->post(route('reports.store', $listing), $this->reportPayload())
            ->assertRedirect(route('public.listings.show', $listing));

        $this->assertSame(1, DB::table('reports')
            ->where('reporter_id', $reporter->id)
            ->where('listing_id', $listing->id)
            ->where('status', 'PENDING')
            ->count());
        $this->assertSame(2, DB::table('reports')->where('reporter_id', $reporter->id)->where('listing_id', $listing->id)->count());
    }

    public function test_expected_generated_unique_violation_is_translated_but_unrelated_database_errors_propagate(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $duplicate = new PDOException('Duplicate entry for reports_one_pending_per_reporter_listing_unique', 1062);
        $duplicate->errorInfo = ['23000', 1062, 'reports_one_pending_per_reporter_listing_unique'];
        $duplicateQuery = new QueryException('mysql', 'insert into reports', [], $duplicate);
        Report::creating(function () use ($duplicateQuery): never {
            throw $duplicateQuery;
        });

        try {
            $this->actingAs($reporter)
                ->from(route('public.listings.show', $listing))
                ->post(route('reports.store', $listing), $this->reportPayload())
                ->assertRedirect(route('public.listings.show', $listing))
                ->assertSessionHasErrors('report');
        } finally {
            Event::forget('eloquent.creating: '.Report::class);
        }

        $this->assertDatabaseCount('reports', 0);

        $unrelated = new PDOException('Forced unrelated database failure.', 1644);
        $unrelated->errorInfo = ['HY000', 1644, 'Forced unrelated database failure.'];
        $unrelatedQuery = new QueryException('mysql', 'insert into reports', [], $unrelated);
        Report::creating(function () use ($unrelatedQuery): never {
            throw $unrelatedQuery;
        });

        try {
            $this->actingAs($reporter)
                ->post(route('reports.store', $listing), $this->reportPayload())
                ->assertStatus(500);
        } finally {
            Event::forget('eloquent.creating: '.Report::class);
        }

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_mysql_unique_index_rejects_a_duplicate_pending_report_even_when_service_is_bypassed(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));
        $this->actingAs($reporter)->post(route('reports.store', $listing), $this->reportPayload())->assertRedirect();

        try {
            DB::table('reports')->insert([
                'reporter_id' => $reporter->id,
                'listing_id' => $listing->id,
                'reason_id' => $this->activeReasonId(),
                'description' => null,
                'status' => 'PENDING',
                'handled_by' => null,
                'resolution_reason' => null,
                'handled_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The generated-column unique index must reject a duplicate pending report.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('reports_one_pending_per_reporter_listing_unique', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('reports')
            ->where('reporter_id', $reporter->id)
            ->where('listing_id', $listing->id)
            ->where('status', 'PENDING')
            ->count());
    }

    public function test_optional_description_obeys_the_mysql_text_byte_limit(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $listing = $this->listing($this->userWithRoles(['LANDLORD']));

        $this->actingAs($reporter)
            ->from(route('public.listings.show', $listing))
            ->post(route('reports.store', $listing), [
                'reason_id' => $this->activeReasonId(),
                'description' => str_repeat('á', 32768),
            ])
            ->assertRedirect(route('public.listings.show', $listing))
            ->assertSessionHasErrors('description');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_report_submission_is_rate_limited(): void
    {
        $reporter = $this->userWithRoles(['RENTER']);
        $landlord = $this->userWithRoles(['LANDLORD']);

        foreach (range(1, 5) as $index) {
            $listing = $this->listing($landlord, "Tin giới hạn {$index}");
            $this->actingAs($reporter)
                ->post(route('reports.store', $listing), $this->reportPayload())
                ->assertRedirect();
        }

        $sixthListing = $this->listing($landlord, 'Tin vượt giới hạn');
        $this->actingAs($reporter)
            ->post(route('reports.store', $sixthListing), $this->reportPayload())
            ->assertTooManyRequests();

        $this->assertSame(5, DB::table('reports')->where('reporter_id', $reporter->id)->count());
    }
}
