<?php

namespace Tests\Feature\Console;

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
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Tests\TestCase;

class AutoCancelOverdueAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private User $renter;

    private Listing $listing;

    private int $categoryId;

    private int $wardId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::create(2026, 9, 24, 10, 0, 0, 'Asia/Ho_Chi_Minh'));
        $this->seed(RoleSeeder::class);

        $this->categoryId = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true])->id;
        $province = Province::query()->create(['code' => 'HN', 'name' => 'Hà Nội', 'is_active' => true]);
        $district = District::query()->create([
            'province_id' => $province->id,
            'code' => 'CG',
            'name' => 'Cầu Giấy',
            'is_active' => true,
        ]);
        $this->wardId = Ward::query()->create([
            'district_id' => $district->id,
            'code' => 'DV',
            'name' => 'Dịch Vọng',
            'is_active' => true,
        ])->id;
        $this->landlord = $this->userWithRole('LANDLORD', 'landlord@example.test');
        $this->renter = $this->userWithRole('RENTER', 'renter@example.test');
        $this->listing = $this->listing();
    }

    public function test_command_only_cancels_overdue_pending_rows_and_is_idempotent(): void
    {
        $overdue = $this->appointment($this->slot($this->localNow()->subMinute()), $this->renter, 'PENDING');
        $future = $this->appointment($this->slot($this->localNow()->addHour()), $this->userWithRole('RENTER'), 'PENDING');
        $accepted = $this->appointment($this->slot($this->localNow()->subHour()), $this->userWithRole('RENTER'), 'ACCEPTED');
        $rejected = $this->appointment($this->slot($this->localNow()->subHours(2)), $this->userWithRole('RENTER'), 'REJECTED');
        $cancelled = $this->appointment($this->slot($this->localNow()->subHours(3)), $this->userWithRole('RENTER'), 'CANCELLED');
        $completed = $this->appointment($this->slot($this->localNow()->subHours(4)), $this->userWithRole('RENTER'), 'COMPLETED');

        $this->artisan('appointments:auto-cancel')->assertSuccessful();

        $this->assertSame('AUTO_CANCELLED', $overdue->fresh()->status);
        $this->assertNull($overdue->fresh()->cancelled_by);
        $this->assertSame('VIEWING_TIME_PASSED', $overdue->fresh()->cancellation_reason);
        $this->assertNotNull($overdue->fresh()->cancelled_at);
        $this->assertSame('PENDING', $future->fresh()->status);
        $this->assertSame('ACCEPTED', $accepted->fresh()->status);
        $this->assertSame('REJECTED', $rejected->fresh()->status);
        $this->assertSame('CANCELLED', $cancelled->fresh()->status);
        $this->assertSame('COMPLETED', $completed->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->renter->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $overdue->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->landlord->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $overdue->id,
        ]);

        $this->artisan('appointments:auto-cancel')->assertSuccessful();
        $this->assertSame(2, AppNotification::query()->where('entity_id', $overdue->id)->count());
    }

    public function test_scheduler_registration_uses_a_fifteen_minute_cadence(): void
    {
        $event = collect(Schedule::events())->first(
            fn ($event): bool => str_contains($event->command, 'appointments:auto-cancel'),
        );

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    public function test_notification_failure_for_one_recipient_does_not_fail_other_delivery_or_cancel(): void
    {
        $appointment = $this->appointment($this->slot($this->localNow()->subMinute()), $this->renter, 'PENDING');
        AppNotification::creating(function (AppNotification $notification): void {
            if ((int) $notification->user_id === (int) $this->renter->id) {
                throw new RuntimeException('Forced renter notification failure.');
            }
        });
        Log::shouldReceive('warning')->once()->with(
            'Appointment notification could not be created.',
            \Mockery::on(fn (array $context): bool => $context['appointment_id'] === $appointment->id
                && $context['notification_type'] === 'APPOINTMENT_AUTO_CANCELLED'),
        );

        try {
            $this->artisan('appointments:auto-cancel')->assertSuccessful();
        } finally {
            Event::forget('eloquent.creating: '.AppNotification::class);
        }

        $this->assertSame('AUTO_CANCELLED', $appointment->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->landlord->id,
            'notification_type' => 'APPOINTMENT_AUTO_CANCELLED',
            'entity_id' => $appointment->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->renter->id,
            'entity_id' => $appointment->id,
        ]);
    }

    private function listing(): Listing
    {
        $listing = new Listing;
        $listing->forceFill([
            'landlord_id' => $this->landlord->id,
            'category_id' => $this->categoryId,
            'title' => 'Phòng cần xem',
            'description' => 'Mô tả',
            'monthly_rent' => '3000000.00',
            'area_m2' => '20.00',
            'max_occupants' => 2,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'gender_requirement' => 'ANY',
            'ward_id' => $this->wardId,
            'street_address' => '1 Đường Mẫu',
            'occupancy_status' => 'AVAILABLE',
            'visibility_status' => 'VISIBLE',
            'view_count' => 0,
        ])->save();
        $moderation = $listing->moderations()->create([
            'version_no' => 1,
            'status' => 'APPROVED',
            'reviewed_by' => $this->landlord->id,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();

        return $listing->fresh();
    }

    private function slot(Carbon $startsAt): ViewingSlot
    {
        return ViewingSlot::query()->create([
            'listing_id' => $this->listing->id,
            'viewing_date' => $startsAt->toDateString(),
            'start_time' => $startsAt->format('H:i:s'),
            'end_time' => $startsAt->copy()->addHour()->format('H:i:s'),
            'status' => 'OPEN',
        ]);
    }

    private function appointment(ViewingSlot $slot, User $renter, string $status): Appointment
    {
        return Appointment::query()->create([
            'slot_id' => $slot->id,
            'renter_id' => $renter->id,
            'status' => $status,
        ]);
    }

    private function userWithRole(string $role, ?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->roles()->attach(Role::query()->where('code', $role)->value('id'), ['assigned_at' => now()]);

        return $user;
    }

    private function localNow(): Carbon
    {
        return Carbon::now('Asia/Ho_Chi_Minh');
    }
}
