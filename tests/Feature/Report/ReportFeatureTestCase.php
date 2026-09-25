<?php

namespace Tests\Feature\Report;

use App\Models\Appointment;
use App\Models\District;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Models\Ward;
use Carbon\CarbonImmutable;
use Database\Seeders\ReportReasonSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class ReportFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected RoomCategory $category;

    protected Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RoleSeeder::class, ReportReasonSeeder::class]);

        $this->category = RoomCategory::query()->create([
            'name' => 'Phòng trọ',
            'is_active' => true,
        ]);
        $province = DB::table('provinces')->insertGetId([
            'code' => fake()->unique()->bothify('P####'),
            'name' => 'Hà Nội',
            'is_active' => true,
        ]);
        $district = District::query()->create([
            'province_id' => $province,
            'code' => fake()->unique()->bothify('D####'),
            'name' => 'Cầu Giấy',
            'is_active' => true,
        ]);
        $this->ward = Ward::query()->create([
            'district_id' => $district->id,
            'code' => fake()->unique()->bothify('W####'),
            'name' => 'Dịch Vọng',
            'is_active' => true,
        ]);
    }

    /** @param array<int, string> $roles */
    protected function userWithRoles(array $roles, ?string $name = null): User
    {
        $user = User::factory()->create();
        $user->profile()->create([
            'full_name' => $name ?? fake()->name(),
            'contact_address' => '12 Nguyễn Trãi, Hà Nội',
        ]);

        foreach ($roles as $role) {
            $user->roles()->attach(Role::query()->where('code', $role)->value('id'), [
                'assigned_at' => now(),
            ]);
        }

        return $user->fresh(['profile']);
    }

    /** @param array<string, mixed> $overrides */
    protected function listing(User $landlord, string $title = 'Phòng gần trung tâm', array $overrides = []): Listing
    {
        $moderationStatus = $overrides['moderation_status'] ?? 'APPROVED';
        unset($overrides['moderation_status']);

        $listing = new Listing;
        $listing->forceFill(array_merge([
            'landlord_id' => $landlord->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'description' => 'Mô tả chi tiết căn phòng',
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
            'expires_at' => now()->addDays(10),
            'deleted_at' => null,
            'view_count' => 0,
        ], $overrides))->save();

        $moderation = $listing->moderations()->create([
            'version_no' => 1,
            'status' => $moderationStatus,
            'reviewed_by' => $moderationStatus === 'PENDING' ? null : $landlord->id,
            'rejection_reason' => $moderationStatus === 'REJECTED' ? 'Lý do từ chối cũ.' : null,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => $moderationStatus === 'PENDING' ? null : now()->subHour(),
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();

        return $listing->fresh(['currentModeration']);
    }

    protected function activeReasonId(): int
    {
        return (int) DB::table('report_reasons')->where('code', 'WRONG_ADDRESS')->value('id');
    }

    /** @param array<string, mixed> $overrides */
    protected function report(User $reporter, Listing $listing, array $overrides = []): Report
    {
        return Report::query()->create(array_merge([
            'reporter_id' => $reporter->id,
            'listing_id' => $listing->id,
            'reason_id' => $this->activeReasonId(),
            'description' => 'Thông tin cần được kiểm tra.',
            'status' => 'PENDING',
        ], $overrides));
    }

    protected function appointment(Listing $listing, User $renter, int $hoursFromNow = 3, string $status = 'PENDING'): Appointment
    {
        $start = CarbonImmutable::now(ViewingSlot::TIMEZONE)->addHours($hoursFromNow);
        $slot = ViewingSlot::query()->create([
            'listing_id' => $listing->id,
            'viewing_date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->addHour()->format('H:i:s'),
            'status' => 'OPEN',
        ]);

        return Appointment::query()->create([
            'slot_id' => $slot->id,
            'renter_id' => $renter->id,
            'status' => $status,
        ]);
    }

    /** @return array{reason_id: int, description: string} */
    protected function reportPayload(): array
    {
        return [
            'reason_id' => $this->activeReasonId(),
            'description' => 'Thông tin địa chỉ không khớp.',
        ];
    }
}
