<?php

namespace Tests\Feature\Appointment;

use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\District;
use App\Models\Listing;
use App\Models\Province;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Models\Ward;
use App\Services\AppointmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PDOException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ViewingSlotAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private RoomCategory $category;

    private Ward $ward;

    private User $landlord;

    private User $renter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::create(2026, 9, 24, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $this->category = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true]);
        $province = Province::query()->create(['code' => 'HN', 'name' => 'Hà Nội', 'is_active' => true]);
        $district = District::query()->create([
            'province_id' => $province->id,
            'code' => 'CG',
            'name' => 'Cầu Giấy',
            'is_active' => true,
        ]);
        $this->ward = Ward::query()->create([
            'district_id' => $district->id,
            'code' => 'DV',
            'name' => 'Dịch Vọng',
            'is_active' => true,
        ]);

        $this->landlord = $this->userWithRoles(['LANDLORD'], 'landlord@example.test', 'Chủ trọ', '0901234567');
        $this->renter = $this->userWithRoles(['RENTER'], 'renter@example.test', 'Người thuê', '0907654321');
    }

    public function test_guest_and_non_renter_cannot_book_or_view_renter_appointments(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));

        $this->get(route('appointments.index'))->assertRedirect('/login');
        $this->post(route('appointments.store', $slot))->assertRedirect('/login');

        foreach (['LANDLORD', 'ADMIN', 'SUPER_ADMIN'] as $role) {
            $user = $this->userWithRoles([$role]);
            $this->actingAs($user)->get(route('appointments.index'))->assertForbidden();
            $this->actingAs($user)->post(route('appointments.store', $slot))->assertForbidden();
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_renter_and_dual_capability_account_can_see_their_own_appointments(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $appointment = $this->appointment($slot, $this->renter);
        $dualCapability = $this->userWithRoles(['RENTER', 'LANDLORD']);
        $secondSlot = $this->slot($listing, $this->localNow()->addHours(4));

        $this->actingAs($this->renter)->get(route('appointments.index'))->assertOk()->assertSee($listing->title);
        $this->actingAs($dualCapability)->get(route('appointments.index'))->assertOk();
        $this->actingAs($dualCapability)->post(route('appointments.store', $secondSlot))->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'renter_id' => $this->renter->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_admin_only_roles_are_denied_but_combined_capability_roles_remain_usable(): void
    {
        $adminOnly = $this->userWithRoles(['ADMIN']);
        $superAdminOnly = $this->userWithRoles(['SUPER_ADMIN']);
        $adminLandlord = $this->userWithRoles(['SUPER_ADMIN', 'LANDLORD']);
        $adminOwnedListing = $this->listing($adminLandlord, 'Admin account landlord listing');
        $slot = $this->slot($adminOwnedListing, $this->localNow()->addHours(3));
        $adminRenter = $this->userWithRoles(['ADMIN', 'RENTER']);

        foreach ([$adminOnly, $superAdminOnly] as $admin) {
            $this->actingAs($admin)->get(route('appointments.index'))->assertForbidden();
            $this->actingAs($admin)->post(route('appointments.store', $slot))->assertForbidden();
            $this->actingAs($admin)->get(route('landlord.appointments.index'))->assertForbidden();
            $this->actingAs($admin)->get(route('landlord.viewing-slots.index', $adminOwnedListing))->assertForbidden();
        }

        $this->actingAs($adminRenter)->post(route('appointments.store', $slot))->assertRedirect(route('appointments.index'));
        $this->actingAs($adminRenter)->get(route('appointments.index'))->assertOk()->assertSee($adminOwnedListing->title);
        $this->actingAs($adminLandlord)->get(route('landlord.appointments.index'))->assertOk()->assertSee($adminOwnedListing->title);
        $this->actingAs($adminLandlord)->get(route('landlord.viewing-slots.index', $adminOwnedListing))->assertOk();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_landlord_can_create_and_list_future_slots_for_an_owned_listing(): void
    {
        $listing = $this->listing($this->landlord);
        $startsAt = $this->localNow()->addHours(3);

        $this->actingAs($this->landlord)
            ->post(route('landlord.viewing-slots.store', $listing), $this->slotData($startsAt))
            ->assertRedirect(route('landlord.viewing-slots.index', $listing));

        $this->assertDatabaseHas('viewing_slots', [
            'listing_id' => $listing->id,
            'viewing_date' => $startsAt->toDateString(),
            'start_time' => $startsAt->format('H:i:s'),
            'status' => 'OPEN',
        ]);

        $this->actingAs($this->landlord)
            ->get(route('landlord.viewing-slots.index', $listing))
            ->assertOk()
            ->assertSee($listing->title)
            ->assertSee($startsAt->format('H:i'));
    }

    public function test_landlord_cannot_manage_another_landlords_slots_or_appointments(): void
    {
        $listing = $this->listing($this->landlord);
        $otherLandlord = $this->userWithRoles(['LANDLORD']);
        $otherListing = $this->listing($otherLandlord, 'Tin của chủ khác');
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $appointment = $this->appointment($slot, $this->renter);

        $this->actingAs($otherLandlord)
            ->get(route('landlord.viewing-slots.index', $listing))
            ->assertForbidden();
        $this->actingAs($otherLandlord)
            ->post(route('landlord.viewing-slots.store', $listing), $this->slotData($this->localNow()->addHours(4)))
            ->assertForbidden();
        $this->actingAs($otherLandlord)
            ->post(route('landlord.appointments.accept', $appointment))
            ->assertForbidden();

        $this->assertDatabaseHas('viewing_slots', ['id' => $slot->id, 'listing_id' => $listing->id]);
        $this->assertDatabaseMissing('viewing_slots', ['listing_id' => $otherListing->id, 'status' => 'OPEN']);
        $this->assertSame('PENDING', $appointment->fresh()->status);
    }

    public function test_slot_creation_rejects_non_future_or_reversed_time_values(): void
    {
        $listing = $this->listing($this->landlord);
        $now = $this->localNow();

        $this->actingAs($this->landlord)
            ->from(route('landlord.viewing-slots.index', $listing))
            ->post(route('landlord.viewing-slots.store', $listing), $this->slotData($now->subMinutes(1)))
            ->assertRedirect(route('landlord.viewing-slots.index', $listing))
            ->assertSessionHasErrors('viewing_date');

        $reversed = $this->slotData($now->addHours(4));
        $reversed['start_time'] = '15:00';
        $reversed['end_time'] = '14:00';

        $this->actingAs($this->landlord)
            ->from(route('landlord.viewing-slots.index', $listing))
            ->post(route('landlord.viewing-slots.store', $listing), $reversed)
            ->assertRedirect(route('landlord.viewing-slots.index', $listing))
            ->assertSessionHasErrors('end_time');

        $this->assertDatabaseCount('viewing_slots', 0);
    }

    public function test_exact_duplicate_and_overlapping_open_slots_are_rejected_but_adjacent_slots_are_valid(): void
    {
        $listing = $this->listing($this->landlord);
        $startsAt = $this->localNow()->addHours(3);
        $first = $this->slotData($startsAt);

        $this->actingAs($this->landlord)->post(route('landlord.viewing-slots.store', $listing), $first)->assertRedirect();
        $this->actingAs($this->landlord)
            ->from(route('landlord.viewing-slots.index', $listing))
            ->post(route('landlord.viewing-slots.store', $listing), $first)
            ->assertSessionHasErrors('start_time');

        $overlap = $this->slotData($startsAt->addMinutes(30));
        $this->actingAs($this->landlord)
            ->from(route('landlord.viewing-slots.index', $listing))
            ->post(route('landlord.viewing-slots.store', $listing), $overlap)
            ->assertSessionHasErrors('start_time');

        $adjacent = $this->slotData($startsAt->addHours(1));
        $this->actingAs($this->landlord)
            ->post(route('landlord.viewing-slots.store', $listing), $adjacent)
            ->assertRedirect();

        $this->assertDatabaseCount('viewing_slots', 2);
    }

    public function test_closed_slots_do_not_block_overlap_and_can_be_reopened_only_without_overlap_or_active_booking(): void
    {
        $listing = $this->listing($this->landlord);
        $startsAt = $this->localNow()->addHours(3);
        $closed = $this->slot($listing, $startsAt, 'CLOSED');
        $overlapping = $this->slotData($startsAt->addMinutes(15));

        $this->actingAs($this->landlord)
            ->post(route('landlord.viewing-slots.store', $listing), $overlapping)
            ->assertRedirect();

        $overlapSlot = ViewingSlot::query()->where('listing_id', $listing->id)->where('status', 'OPEN')->firstOrFail();
        $this->actingAs($this->landlord)
            ->patch(route('landlord.viewing-slots.status', [$listing, $closed]), ['status' => 'OPEN'])
            ->assertSessionHasErrors('status');

        $overlapSlot->status = 'CLOSED';
        $overlapSlot->save();
        $this->actingAs($this->landlord)
            ->patch(route('landlord.viewing-slots.status', [$listing, $closed]), ['status' => 'OPEN'])
            ->assertRedirect();

        $active = $this->appointment($closed, $this->renter);
        $this->actingAs($this->landlord)
            ->patch(route('landlord.viewing-slots.status', [$listing, $closed]), ['status' => 'CLOSED'])
            ->assertSessionHasErrors('status');

        $active->status = 'REJECTED';
        $active->save();
        $this->actingAs($this->landlord)
            ->patch(route('landlord.viewing-slots.status', [$listing, $closed]), ['status' => 'CLOSED'])
            ->assertRedirect();

        $this->actingAs($this->landlord)
            ->patch(route('landlord.viewing-slots.status', [$listing, $closed]), ['status' => 'OPEN'])
            ->assertRedirect();

        $this->assertSame('OPEN', $closed->fresh()->status);
    }

    public function test_public_detail_shows_only_currently_bookable_slots_and_never_appointment_history(): void
    {
        $listing = $this->listing($this->landlord, 'Phòng có lịch xem');
        $eligible = $this->slot($listing, $this->localNow()->addHours(2));
        $this->slot($listing, $this->localNow()->addHour());
        $this->slot($listing, $this->localNow()->addDays(31));
        $this->slot($listing, $this->localNow()->addHours(4), 'CLOSED');
        $this->appointment($this->slot($listing, $this->localNow()->addHours(5)), $this->renter);

        $response = $this->get(route('public.listings.show', $listing))->assertOk();
        $response->assertSee($eligible->startAtVietnam()->format('H:i'))
            ->assertDontSee($this->renter->profile->full_name)
            ->assertDontSee($this->renter->email)
            ->assertSee(__('ui.appointments.login_to_book'));

        $publicListing = $response->viewData('listing');
        $this->assertTrue($publicListing->relationLoaded('bookableViewingSlots'));
        $this->assertCount(1, $publicListing->bookableViewingSlots);

        $ownerResponse = $this->actingAs($this->landlord)->get(route('public.listings.show', $listing))->assertOk();
        $ownerResponse->assertDontSee(route('appointments.store', $eligible));
    }

    public function test_renter_can_book_an_eligible_slot_and_landlord_is_notified_after_commit(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(2));

        $this->actingAs($this->renter)
            ->post(route('appointments.store', $slot), [
                'renter_note' => 'Tôi có thể đến đúng giờ.',
                'renter_id' => $this->landlord->id,
                'status' => 'ACCEPTED',
            ])
            ->assertRedirect(route('appointments.index'));

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('PENDING', $appointment->status);
        $this->assertSame($this->renter->id, $appointment->renter_id);
        $this->assertSame('Tôi có thể đến đúng giờ.', $appointment->renter_note);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->landlord->id,
            'notification_type' => 'APPOINTMENT_BOOKED',
            'entity_id' => $appointment->id,
        ]);
    }

    public function test_booking_enforces_two_hour_and_rolling_30_day_inclusive_boundaries(): void
    {
        $listing = $this->listing($this->landlord);
        $twoHours = $this->slot($listing, $this->localNow()->addHours(2));
        $thirtyDays = $this->slot($listing, $this->localNow()->addHours(720));
        $lessThanTwoHours = $this->slot($listing, $this->localNow()->addMinutes(119));
        $beyondThirtyDays = $this->slot($listing, $this->localNow()->addHours(721));

        $this->actingAs($this->renter)->post(route('appointments.store', $twoHours))->assertRedirect();
        $this->actingAs($this->renter)->post(route('appointments.store', $thirtyDays))->assertRedirect();
        $this->actingAs($this->renter)
            ->post(route('appointments.store', $lessThanTwoHours))
            ->assertSessionHasErrors('slot');
        $this->actingAs($this->renter)
            ->post(route('appointments.store', $beyondThirtyDays))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_renter_cannot_book_own_listing_or_a_non_public_closed_or_already_active_slot(): void
    {
        $ownListing = $this->listing($this->renter, 'Tin của người thuê');
        $ownSlot = $this->slot($ownListing, $this->localNow()->addHours(3));
        $otherListing = $this->listing($this->landlord);
        $closedSlot = $this->slot($otherListing, $this->localNow()->addHours(4), 'CLOSED');
        $activeSlot = $this->slot($otherListing, $this->localNow()->addHours(5));
        $this->appointment($activeSlot, $this->userWithRoles(['RENTER']));
        $hiddenListing = $this->listing($this->landlord, 'Tin đang ẩn', ['visibility_status' => 'HIDDEN']);
        $hiddenSlot = $this->slot($hiddenListing, $this->localNow()->addHours(6));
        $pastSlot = $this->slot($otherListing, $this->localNow()->subMinutes(1));
        $invalidSlot = $this->slot($otherListing, $this->localNow()->addHours(7));
        $invalidSlot->end_time = $this->localNow()->addHours(7)->subMinute()->format('H:i:s');
        $invalidSlot->save();

        $this->actingAs($this->renter)->post(route('appointments.store', $ownSlot))->assertSessionHasErrors('slot');
        $this->actingAs($this->renter)->post(route('appointments.store', $closedSlot))->assertSessionHasErrors('slot');
        $this->actingAs($this->renter)->post(route('appointments.store', $activeSlot))->assertStatus(409);
        $this->actingAs($this->renter)->post(route('appointments.store', $hiddenSlot))->assertSessionHasErrors('slot');
        $this->actingAs($this->renter)->post(route('appointments.store', $pastSlot))->assertSessionHasErrors('slot');
        $this->actingAs($this->renter)->post(route('appointments.store', $invalidSlot))->assertSessionHasErrors('slot');

        $this->assertSame(1, Appointment::query()->where('slot_id', $activeSlot->id)->count());
    }

    public function test_booking_rechecks_moderation_occupancy_visibility_deletion_and_expiry(): void
    {
        $unbookableListings = [
            $this->listing($this->landlord, 'Tin chưa duyệt', ['moderation_status' => 'PENDING']),
            $this->listing($this->landlord, 'Tin đã thuê', ['occupancy_status' => 'RENTED']),
            $this->listing($this->landlord, 'Tin đã xóa', ['deleted_at' => now()]),
            $this->listing($this->landlord, 'Tin hết hạn', ['expires_at' => now()->subMinute()]),
        ];

        foreach ($unbookableListings as $index => $listing) {
            $slot = $this->slot($listing, $this->localNow()->addHours(3 + $index));
            $this->actingAs($this->renter)
                ->post(route('appointments.store', $slot))
                ->assertSessionHasErrors('slot');
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_terminal_history_allows_rebooking_and_an_active_appointment_blocks_another_renter(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $history = $this->appointment($slot, $this->renter, 'REJECTED');

        $this->actingAs($this->renter)->post(route('appointments.store', $slot))->assertRedirect();
        $secondRenter = $this->userWithRoles(['RENTER']);
        $this->actingAs($secondRenter)->post(route('appointments.store', $slot))->assertStatus(409);

        $this->assertSame('REJECTED', $history->fresh()->status);
        $this->assertSame(2, Appointment::query()->where('slot_id', $slot->id)->count());
        $this->assertSame(1, Appointment::query()->where('slot_id', $slot->id)->whereIn('status', ['PENDING', 'ACCEPTED'])->count());
    }

    public function test_renter_may_book_multiple_future_slots_for_the_same_listing(): void
    {
        $listing = $this->listing($this->landlord);
        $firstSlot = $this->slot($listing, $this->localNow()->addHours(3));
        $secondSlot = $this->slot($listing, $this->localNow()->addHours(5));

        $this->actingAs($this->renter)->post(route('appointments.store', $firstSlot))->assertRedirect();
        $this->actingAs($this->renter)->post(route('appointments.store', $secondSlot))->assertRedirect();

        $this->assertSame(2, Appointment::query()->where('renter_id', $this->renter->id)->whereIn('status', ['PENDING', 'ACCEPTED'])->count());
    }

    public function test_mysql_generated_unique_index_rejects_a_second_active_appointment_even_when_service_is_bypassed(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $this->appointment($slot, $this->renter);

        try {
            $this->appointment($slot, $this->userWithRoles(['RENTER']), 'ACCEPTED');
            $this->fail('The MySQL active-slot unique index must reject a second active appointment.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('appointments_one_active_per_slot_unique', $exception->getMessage());
        }

        $this->assertSame(1, Appointment::query()->where('slot_id', $slot->id)->whereIn('status', ['PENDING', 'ACCEPTED'])->count());
    }

    public function test_concurrent_bookings_and_overlapping_slot_creates_are_serialized_by_listing_locks(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $secondRenter = $this->userWithRoles(['RENTER']);
        $listingId = $listing->id;
        $slotId = $slot->id;
        $overlapListingId = null;

        $script = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::create(2026, 9, 24, 10, 0, 0, 'Asia/Ho_Chi_Minh'));
file_put_contents($argv[6], 'ready');

try {
    $actor = App\Models\User::query()->findOrFail((int) $argv[4]);

    if ($argv[2] === 'booking') {
        app(App\Services\AppointmentService::class)->book(
            App\Models\ViewingSlot::query()->findOrFail((int) $argv[3]),
            $actor,
            null,
        );
        echo 'BOOKED';
    } else {
        app(App\Services\ViewingSlotService::class)->create(
            App\Models\Listing::query()->findOrFail((int) $argv[3]),
            $actor,
            json_decode($argv[5], true, 512, JSON_THROW_ON_ERROR),
        );
        echo 'CREATED';
    }
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo 'CONFLICT';
} catch (Illuminate\Validation\ValidationException) {
    echo 'OVERLAP';
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
PHP;

        $runWorkersBehindListingLock = function (int $lockedListingId, array $workers) use ($script): array {
            $processes = [];
            $readyPaths = [];
            $listingLockHeld = false;

            try {
                DB::beginTransaction();
                $listingLockHeld = true;
                Listing::query()->whereKey($lockedListingId)->lockForUpdate()->firstOrFail();

                foreach ($workers as $worker) {
                    $readyPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'roomrental-appointment-race-'.bin2hex(random_bytes(12));
                    $readyPaths[] = $readyPath;
                    $process = new Process([
                        PHP_BINARY,
                        '-r',
                        $script,
                        base_path(),
                        $worker['mode'],
                        (string) $worker['target_id'],
                        (string) $worker['actor_id'],
                        json_encode($worker['data'] ?? [], JSON_THROW_ON_ERROR),
                        $readyPath,
                    ], base_path());
                    $process->setTimeout(30);
                    $process->start();
                    $processes[] = $process;
                }

                $readyDeadline = microtime(true) + 30;

                while (count(array_filter($readyPaths, 'is_file')) < count($readyPaths) && microtime(true) < $readyDeadline) {
                    usleep(10_000);
                }

                $this->assertCount(count($readyPaths), array_filter($readyPaths, 'is_file'), 'Both workers must reach the start barrier.');
                usleep(100_000);
                DB::commit();
                $listingLockHeld = false;

                $outcomes = [];

                foreach ($processes as $process) {
                    $process->wait();
                    $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                    $outcomes[] = $process->getOutput();
                }

                sort($outcomes);

                return $outcomes;
            } finally {
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }

                foreach ($readyPaths as $readyPath) {
                    if (is_file($readyPath)) {
                        unlink($readyPath);
                    }
                }

                if ($listingLockHeld && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        };

        try {
            // Commit fixtures so independent MySQL connections can see them.
            DB::commit();
            $outcomes = $runWorkersBehindListingLock($listingId, [
                ['mode' => 'booking', 'target_id' => $slotId, 'actor_id' => $this->renter->id],
                ['mode' => 'booking', 'target_id' => $slotId, 'actor_id' => $secondRenter->id],
            ]);
            $this->assertSame(['BOOKED', 'CONFLICT'], $outcomes);
            $this->assertSame(1, Appointment::query()
                ->where('slot_id', $slotId)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->count());

            $overlapListing = $this->listing($this->landlord, 'Concurrent overlap listing');
            $overlapListingId = $overlapListing->id;
            $date = $this->localNow()->addDay()->toDateString();
            $overlapOutcomes = $runWorkersBehindListingLock($overlapListingId, [
                ['mode' => 'slot', 'target_id' => $overlapListingId, 'actor_id' => $this->landlord->id, 'data' => [
                    'viewing_date' => $date,
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                ]],
                ['mode' => 'slot', 'target_id' => $overlapListingId, 'actor_id' => $this->landlord->id, 'data' => [
                    'viewing_date' => $date,
                    'start_time' => '10:30',
                    'end_time' => '11:30',
                ]],
            ]);

            $this->assertSame(['CREATED', 'OVERLAP'], $overlapOutcomes);
            $this->assertSame(1, ViewingSlot::query()
                ->where('listing_id', $overlapListingId)
                ->where('viewing_date', $date)
                ->where('status', 'OPEN')
                ->count());
        } finally {
            $listingIds = array_filter([$listingId, $overlapListingId]);
            $slotIds = DB::table('viewing_slots')->whereIn('listing_id', $listingIds)->pluck('id');
            $appointmentIds = DB::table('appointments')->whereIn('slot_id', $slotIds)->pluck('id');
            DB::table('notifications')->where('entity_type', 'appointment')->whereIn('entity_id', $appointmentIds)->delete();
            DB::table('appointments')->whereIn('slot_id', $slotIds)->delete();
            DB::table('viewing_slots')->whereIn('listing_id', $listingIds)->delete();
            DB::table('listings')->whereIn('id', $listingIds)->update(['current_moderation_id' => null]);
            DB::table('listing_moderations')->whereIn('listing_id', $listingIds)->delete();
            DB::table('listings')->whereIn('id', $listingIds)->delete();

            $userIds = [$this->landlord->id, $this->renter->id, $secondRenter->id];
            DB::table('notifications')->whereIn('user_id', $userIds)->delete();
            DB::table('favorites')->whereIn('user_id', $userIds)->delete();
            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
            DB::table('user_profiles')->whereIn('user_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
            DB::table('room_categories')->where('id', $this->category->id)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('listings')->whereColumn('listings.category_id', 'room_categories.id'))
                ->delete();
            $districtId = $this->ward->district_id;
            $provinceId = DB::table('districts')->where('id', $districtId)->value('province_id');
            DB::table('wards')->where('id', $this->ward->id)->delete();
            DB::table('districts')->where('id', $districtId)->delete();
            DB::table('provinces')->where('id', $provinceId)->delete();
        }
    }

    public function test_landlord_can_accept_pending_with_optional_response_and_reject_only_with_required_response(): void
    {
        $listing = $this->listing($this->landlord);
        $accepted = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);
        $rejected = $this->appointment($this->slot($listing, $this->localNow()->addHours(4)), $this->userWithRoles(['RENTER']));

        $this->actingAs($this->landlord)
            ->post(route('landlord.appointments.accept', $accepted))
            ->assertRedirect();
        $this->assertSame('ACCEPTED', $accepted->fresh()->status);
        $this->assertNotNull($accepted->fresh()->responded_at);
        $this->assertNull($accepted->fresh()->landlord_response);

        $this->actingAs($this->landlord)
            ->from(route('landlord.appointments.index'))
            ->post(route('landlord.appointments.reject', $rejected), ['landlord_response' => ''])
            ->assertSessionHasErrors('landlord_response');
        $this->actingAs($this->landlord)
            ->from(route('landlord.appointments.index'))
            ->post(route('landlord.appointments.reject', $rejected), ['landlord_response' => str_repeat('x', 1001)])
            ->assertSessionHasErrors('landlord_response');
        $this->assertSame('PENDING', $rejected->fresh()->status);

        $this->actingAs($this->landlord)
            ->post(route('landlord.appointments.reject', $rejected), ['landlord_response' => 'Tin đã có người thuê.'])
            ->assertRedirect();
        $this->assertSame('REJECTED', $rejected->fresh()->status);
        $this->assertSame('Tin đã có người thuê.', $rejected->fresh()->landlord_response);
        $this->assertNotNull($rejected->fresh()->responded_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->renter->id,
            'notification_type' => 'APPOINTMENT_ACCEPTED',
            'entity_id' => $accepted->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rejected->renter_id,
            'notification_type' => 'APPOINTMENT_REJECTED',
            'entity_id' => $rejected->id,
        ]);
    }

    public function test_renter_can_cancel_own_pending_or_accepted_appointment_only_before_start(): void
    {
        $listing = $this->listing($this->landlord);
        $pending = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);
        $accepted = $this->appointment($this->slot($listing, $this->localNow()->addHours(4)), $this->renter, 'ACCEPTED');
        $started = $this->appointment($this->slot($listing, $this->localNow()->subMinutes(1)), $this->renter);

        $this->actingAs($this->renter)
            ->post(route('appointments.cancel', $pending), [
                'cancellation_reason' => 'Thay đổi kế hoạch.',
                'cancelled_by' => $this->landlord->id,
                'status' => 'COMPLETED',
            ])
            ->assertRedirect();
        $this->actingAs($this->renter)
            ->post(route('appointments.cancel', $accepted))
            ->assertRedirect();
        $this->actingAs($this->renter)
            ->post(route('appointments.cancel', $started))
            ->assertSessionHasErrors('appointment');

        foreach ([$pending, $accepted] as $appointment) {
            $appointment->refresh();
            $this->assertSame('CANCELLED', $appointment->status);
            $this->assertSame($this->renter->id, $appointment->cancelled_by);
            $this->assertNotNull($appointment->cancelled_at);
        }
        $this->assertSame('Thay đổi kế hoạch.', $pending->fresh()->cancellation_reason);
        $this->assertSame('PENDING', $started->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->landlord->id,
            'notification_type' => 'APPOINTMENT_CANCELLED',
            'entity_id' => $pending->id,
        ]);
        $notificationCount = AppNotification::query()->count();
        $this->actingAs($this->renter)->post(route('appointments.cancel', $pending))->assertStatus(409);
        $this->assertSame($notificationCount, AppNotification::query()->count());
    }

    public function test_renter_cannot_cancel_another_renters_appointment(): void
    {
        $listing = $this->listing($this->landlord);
        $appointment = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);
        $otherRenter = $this->userWithRoles(['RENTER']);

        $this->actingAs($otherRenter)->post(route('appointments.cancel', $appointment))->assertForbidden();
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->cancelled_by);
    }

    public function test_only_accepted_appointments_can_be_completed_and_only_after_slot_end(): void
    {
        $listing = $this->listing($this->landlord);
        $startsAt = $this->localNow()->addHours(3);
        $pending = $this->appointment($this->slot($listing, $startsAt), $this->renter);
        $acceptedSlot = $this->slot($listing, $startsAt->addHours(1));
        $accepted = $this->appointment($acceptedSlot, $this->userWithRoles(['RENTER']), 'ACCEPTED');

        $this->actingAs($this->landlord)->post(route('landlord.appointments.complete', $pending))->assertStatus(409);
        $this->actingAs($this->landlord)->post(route('landlord.appointments.complete', $accepted))->assertStatus(409);
        $this->assertSame('PENDING', $pending->fresh()->status);
        $this->assertSame('ACCEPTED', $accepted->fresh()->status);

        $this->travelTo($acceptedSlot->startAtVietnam()->addHours(1));
        $this->actingAs($this->landlord)->post(route('landlord.appointments.complete', $accepted))->assertRedirect();
        $this->assertSame('COMPLETED', $accepted->fresh()->status);
        $this->assertNotNull($accepted->fresh()->completed_at);
        $this->assertNull($accepted->fresh()->cancelled_at);
    }

    public function test_stale_decisions_and_repeated_transitions_do_not_overwrite_history_or_duplicate_notifications(): void
    {
        $listing = $this->listing($this->landlord);
        $appointment = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);

        $this->actingAs($this->landlord)->post(route('landlord.appointments.accept', $appointment))->assertRedirect();
        $notificationCount = AppNotification::query()->count();
        $this->actingAs($this->landlord)
            ->post(route('landlord.appointments.reject', $appointment), ['landlord_response' => 'Không phù hợp.'])
            ->assertStatus(409);
        $this->actingAs($this->landlord)->post(route('landlord.appointments.accept', $appointment))->assertStatus(409);

        $this->assertSame('ACCEPTED', $appointment->fresh()->status);
        $this->assertSame($notificationCount, AppNotification::query()->count());
    }

    public function test_renter_and_landlord_appointment_lists_are_private_and_only_show_permitted_contact_fields(): void
    {
        $ownListing = $this->listing($this->landlord, 'Phòng của chủ trọ');
        $ownAppointment = $this->appointment($this->slot($ownListing, $this->localNow()->addHours(3)), $this->renter);
        $otherRenter = $this->userWithRoles(['RENTER'], 'other-renter@example.test', 'Người thuê khác', '0909999999');
        $otherLandlord = $this->userWithRoles(['LANDLORD'], 'other-landlord@example.test');
        $otherListing = $this->listing($otherLandlord, 'Tin của chủ khác');
        $otherAppointment = $this->appointment($this->slot($otherListing, $this->localNow()->addHours(4)), $otherRenter);

        $renterResponse = $this->actingAs($this->renter)
            ->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Phòng của chủ trọ')
            ->assertDontSee('Tin của chủ khác')
            ->assertSee('0901234567')
            ->assertSee('0901234567')
            ->assertDontSee('landlord@example.test')
            ->assertDontSee('Địa chỉ riêng tư');

        $renterRow = $renterResponse->viewData('appointments')->first();
        $this->assertTrue($renterRow->relationLoaded('slot'));
        $this->assertTrue($renterRow->slot->relationLoaded('listing'));
        $this->assertTrue($renterRow->slot->listing->relationLoaded('coverImage'));
        $this->assertFalse($renterRow->slot->listing->relationLoaded('images'));
        $this->assertFalse($renterRow->slot->listing->relationLoaded('moderations'));

        $landlordResponse = $this->actingAs($this->landlord)
            ->get(route('landlord.appointments.index'))
            ->assertOk()
            ->assertSee('Phòng của chủ trọ')
            ->assertSee('renter@example.test')
            ->assertSee('0907654321')
            ->assertDontSee('Địa chỉ riêng tư')
            ->assertDontSee('Người thuê khác')
            ->assertDontSee('Tin của chủ khác');

        $landlordRow = $landlordResponse->viewData('appointments')->first();
        $this->assertTrue($landlordRow->relationLoaded('renter'));
        $this->assertTrue($landlordRow->renter->relationLoaded('profile'));
        $this->assertArrayNotHasKey('contact_address', $landlordRow->renter->profile->getAttributes());

        $this->assertDatabaseHas('appointments', ['id' => $ownAppointment->id, 'renter_id' => $this->renter->id]);
        $this->assertDatabaseHas('appointments', ['id' => $otherAppointment->id, 'renter_id' => $otherRenter->id]);
    }

    public function test_listing_becoming_rented_atomically_auto_cancels_future_active_appointments_only(): void
    {
        $listing = $this->listing($this->landlord);
        $pending = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);
        $acceptedRenter = $this->userWithRoles(['RENTER']);
        $accepted = $this->appointment($this->slot($listing, $this->localNow()->addHours(4)), $acceptedRenter, 'ACCEPTED');
        $terminal = $this->appointment($this->slot($listing, $this->localNow()->addHours(5)), $this->userWithRoles(['RENTER']), 'REJECTED');
        $past = $this->appointment($this->slot($listing, $this->localNow()->subHours(1)), $this->userWithRoles(['RENTER']));

        $this->actingAs($this->landlord)
            ->patch(route('landlord.listings.occupancy', $listing), ['occupancy_status' => 'RENTED'])
            ->assertRedirect();

        $listing->refresh();
        $this->assertSame('RENTED', $listing->occupancy_status);
        foreach ([$pending, $accepted] as $cancelled) {
            $cancelled->refresh();
            $this->assertSame('AUTO_CANCELLED', $cancelled->status);
            $this->assertSame($this->landlord->id, $cancelled->cancelled_by);
            $this->assertSame('LISTING_RENTED', $cancelled->cancellation_reason);
            $this->assertNotNull($cancelled->cancelled_at);
        }
        $this->assertSame('REJECTED', $terminal->fresh()->status);
        $this->assertSame('PENDING', $past->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->renter->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $pending->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $acceptedRenter->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $accepted->id,
        ]);
    }

    public function test_rented_transition_rolls_back_occupancy_and_appointments_if_a_transition_fails(): void
    {
        $listing = $this->listing($this->landlord);
        $appointment = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);
        $failureFired = false;
        Appointment::updating(function (Appointment $model) use (&$failureFired): void {
            if ($model->status === 'AUTO_CANCELLED') {
                $failureFired = true;
                throw new RuntimeException('Forced appointment transition failure.');
            }
        });

        try {
            $response = $this->actingAs($this->landlord)
                ->patch(route('landlord.listings.occupancy', $listing), ['occupancy_status' => 'RENTED']);
        } finally {
            Event::forget('eloquent.updating: '.Appointment::class);
        }

        $this->assertTrue($failureFired);
        $response->assertStatus(500);
        $this->assertSame('AVAILABLE', $listing->fresh()->occupancy_status);
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertSame(0, AppNotification::query()->count());
    }

    public function test_hidden_pending_moderation_and_expiry_do_not_retroactively_cancel_arranged_appointments(): void
    {
        $listing = $this->listing($this->landlord);
        $appointment = $this->appointment($this->slot($listing, $this->localNow()->addHours(3)), $this->renter);

        $listing->visibility_status = 'HIDDEN';
        $listing->save();
        $this->assertSame('PENDING', $appointment->fresh()->status);

        $listing->currentModeration->status = 'PENDING';
        $listing->currentModeration->reviewed_by = null;
        $listing->currentModeration->reviewed_at = null;
        $listing->currentModeration->save();
        $this->assertSame('PENDING', $appointment->fresh()->status);

        $listing->expires_at = now()->subMinute();
        $listing->save();
        $this->assertSame('PENDING', $appointment->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_failure_after_booking_does_not_rollback_the_appointment(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        AppNotification::creating(function (): void {
            throw new RuntimeException('Forced notification failure.');
        });
        Log::shouldReceive('warning')->once()->with(
            'Appointment notification could not be created.',
            \Mockery::on(fn (array $context): bool => isset($context['appointment_id'], $context['notification_type'])),
        );

        try {
            $this->actingAs($this->renter)->post(route('appointments.store', $slot))->assertRedirect();
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_unrelated_database_error_during_booking_is_rethrown_without_notification(): void
    {
        $listing = $this->listing($this->landlord);
        $slot = $this->slot($listing, $this->localNow()->addHours(3));
        $exception = new QueryException(
            'mysql',
            'insert into `appointments` (`slot_id`, `renter_id`) values (?, ?)',
            [],
            new PDOException('Forced unrelated database failure.', 1644),
        );
        Appointment::creating(function () use ($exception): void {
            throw $exception;
        });

        try {
            app(AppointmentService::class)->book($slot, $this->renter, null);
            $this->fail('An unrelated database error must be rethrown.');
        } catch (QueryException $caught) {
            $this->assertSame('Forced unrelated database failure.', $caught->getPrevious()->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.Appointment::class);
        }

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function listing(User $landlord, string $title = 'Phòng gần trung tâm', array $overrides = []): Listing
    {
        $moderationStatus = $overrides['moderation_status'] ?? 'APPROVED';
        unset($overrides['moderation_status']);

        $listing = new Listing;
        $listing->forceFill(array_merge([
            'landlord_id' => $landlord->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'description' => 'Mô tả căn phòng',
            'monthly_rent' => '3500000.00',
            'deposit_amount' => null,
            'area_m2' => '24.00',
            'max_occupants' => 2,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'gender_requirement' => 'ANY',
            'ward_id' => $this->ward->id,
            'street_address' => '12 Nguyễn Trãi',
            'occupancy_status' => 'AVAILABLE',
            'visibility_status' => 'VISIBLE',
            'expires_at' => null,
            'deleted_at' => null,
            'view_count' => 0,
        ], $overrides))->save();

        $moderation = $listing->moderations()->create([
            'version_no' => 1,
            'status' => $moderationStatus,
            'submitted_at' => now()->subDay(),
            'reviewed_by' => $moderationStatus === 'PENDING' ? null : $landlord->id,
            'reviewed_at' => $moderationStatus === 'PENDING' ? null : now()->subHour(),
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();

        return $listing->fresh();
    }

    private function slot(Listing $listing, Carbon $startsAt, string $status = 'OPEN'): ViewingSlot
    {
        return ViewingSlot::query()->create([
            'listing_id' => $listing->id,
            'viewing_date' => $startsAt->toDateString(),
            'start_time' => $startsAt->format('H:i:s'),
            'end_time' => $startsAt->copy()->addHour()->format('H:i:s'),
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function appointment(ViewingSlot $slot, User $renter, string $status = 'PENDING'): Appointment
    {
        return Appointment::query()->create([
            'slot_id' => $slot->id,
            'renter_id' => $renter->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function slotData(Carbon $startsAt): array
    {
        return [
            'viewing_date' => $startsAt->toDateString(),
            'start_time' => $startsAt->format('H:i'),
            'end_time' => $startsAt->copy()->addHour()->format('H:i'),
        ];
    }

    /** @param array<int, string> $roles */
    private function userWithRoles(array $roles, ?string $email = null, string $name = 'Test user', ?string $phone = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
            'phone' => $phone,
        ]);
        $user->profile()->create([
            'full_name' => $name,
            'zalo_number' => $phone,
            'contact_address' => 'Địa chỉ riêng tư',
        ]);

        foreach ($roles as $role) {
            $roleId = Role::query()->where('code', $role)->value('id');
            $user->roles()->attach($roleId, ['assigned_at' => now()]);
        }

        return $user->fresh(['profile']);
    }

    private function localNow(): Carbon
    {
        return Carbon::now('Asia/Ho_Chi_Minh');
    }
}
