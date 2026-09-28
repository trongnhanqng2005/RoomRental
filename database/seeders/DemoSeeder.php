<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\EnforcementAction;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Province;
use App\Models\Report;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Models\Ward;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'RoomRental-Demo-V1-2026!';

    /** @var array<int, string> */
    private array $placeholderImages = [];

    /** These names/codes are demo-only legacy fixtures, not official geography. */
    private const DEMO_LOCATION_FIXTURE = [
        [
            'code' => 'DEMO-P01',
            'name' => 'Tỉnh demo 01 (fixture legacy V1)',
            'districts' => [
                [
                    'code' => 'D01',
                    'name' => 'Quận demo 01-01',
                    'wards' => [
                        ['code' => 'W01', 'name' => 'Phường demo 01-01-01'],
                        ['code' => 'W02', 'name' => 'Phường demo 01-01-02'],
                    ],
                ],
            ],
        ],
        [
            'code' => 'DEMO-P02',
            'name' => 'Tỉnh demo 02 (fixture legacy V1)',
            'districts' => [
                [
                    'code' => 'D01',
                    'name' => 'Quận demo 02-01',
                    'wards' => [
                        ['code' => 'W01', 'name' => 'Phường demo 02-01-01'],
                        ['code' => 'W02', 'name' => 'Phường demo 02-01-02'],
                    ],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder is disabled in the production environment.');
        }

        if ($this->hasExistingApplicationData()) {
            throw new RuntimeException('DemoSeeder requires a fresh, disposable database. Recreate it before seeding demo data.');
        }

        $writtenPaths = [];

        try {
            DB::transaction(function () use (&$writtenPaths): void {
                $roleIds = Role::query()
                    ->whereIn('code', ['ADMIN', 'LANDLORD', 'RENTER'])
                    ->pluck('id', 'code');

                if ($roleIds->count() !== 3) {
                    throw new RuntimeException('Run the production-safe DatabaseSeeder before DemoSeeder.');
                }

                if (
                    ! RoomCategory::query()->exists()
                    || ! DB::table('fee_types')->where('code', 'ELECTRICITY')->exists()
                    || ! DB::table('fee_units')->where('code', 'PER_KWH')->exists()
                    || ! DB::table('report_reasons')->exists()
                ) {
                    throw new RuntimeException('Run the production-safe DatabaseSeeder before DemoSeeder.');
                }

                $actors = $this->createActors($roleIds->all());
                $locations = $this->createLocations();
                [$amenityIds, $hiddenAmenityId] = $this->createAmenities();
                $categoryIds = $this->createCategories();
                $listings = $this->createListings(
                    $actors['landlord'],
                    $actors['admin'],
                    $locations,
                    $categoryIds,
                    $amenityIds,
                    $hiddenAmenityId,
                    $writtenPaths,
                );

                $this->createAppointments($actors['renter'], $listings['available']);
                $this->createReports($actors, $listings);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($writtenPaths);

            throw $exception;
        }

        $this->command?->info('Demo actors, catalogs, legacy locations, listings, appointments, reports, and placeholder images were created.');
    }

    private function hasExistingApplicationData(): bool
    {
        return DB::table('users')->exists()
            || DB::table('listings')->exists()
            || DB::table('provinces')->exists()
            || DB::table('amenities')->exists();
    }

    /** @param array<string, int> $roleIds
     * @return array{admin: User, landlord: User, renter: User}
     */
    private function createActors(array $roleIds): array
    {
        $now = now();
        $actors = [];

        foreach ([
            'renter' => [
                'email' => 'demo-renter@roomrental.test',
                'phone' => '+84900000001',
                'name' => 'Người thuê demo',
                'roles' => ['RENTER'],
            ],
            'landlord' => [
                'email' => 'demo-landlord@roomrental.test',
                'phone' => '+84900000002',
                'name' => 'Chủ trọ demo',
                'roles' => ['RENTER', 'LANDLORD'],
            ],
            'admin' => [
                'email' => 'demo-admin@roomrental.test',
                'phone' => '+84900000003',
                'name' => 'Admin demo',
                'roles' => ['ADMIN'],
            ],
        ] as $key => $attributes) {
            $user = new User;
            $user->forceFill([
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'password_hash' => Hash::make(self::DEMO_PASSWORD),
                'account_status' => 'ACTIVE',
                'failed_login_count' => 0,
                'login_blocked_until' => null,
                'must_change_password' => false,
                'last_login_at' => null,
            ])->save();
            $user->profile()->create([
                'full_name' => $attributes['name'],
                'contact_address' => $key === 'landlord' ? 'Địa chỉ liên hệ demo' : null,
                'zalo_number' => $key === 'landlord' ? '+84900000002' : null,
            ]);

            foreach ($attributes['roles'] as $roleCode) {
                $user->roles()->attach($roleIds[$roleCode], [
                    'assigned_by' => null,
                    'assigned_at' => $now,
                ]);
            }

            $actors[$key] = $user;
        }

        return $actors;
    }

    /** @return array<int, int> */
    private function createLocations(): array
    {
        $wardIds = [];

        foreach (self::DEMO_LOCATION_FIXTURE as $provinceData) {
            $province = Province::query()->create([
                'code' => $provinceData['code'],
                'name' => $provinceData['name'],
                'is_active' => true,
            ]);

            foreach ($provinceData['districts'] as $districtData) {
                $district = District::query()->create([
                    'province_id' => $province->id,
                    'code' => $districtData['code'],
                    'name' => $districtData['name'],
                    'is_active' => true,
                ]);

                foreach ($districtData['wards'] as $wardData) {
                    $wardIds[] = Ward::query()->create([
                        'district_id' => $district->id,
                        'code' => $wardData['code'],
                        'name' => $wardData['name'],
                        'is_active' => true,
                    ])->id;
                }
            }
        }

        return $wardIds;
    }

    /** @return array{0: array<string, int>, 1: int} */
    private function createAmenities(): array
    {
        $amenityIds = [];
        $hiddenAmenityId = 0;

        foreach (['Wi-Fi', 'Máy lạnh', 'Máy giặt', 'Chỗ để xe', 'Nội thất', 'Ban công'] as $name) {
            $amenity = Amenity::query()->create([
                'name' => $name,
                'description' => 'Tiện nghi mẫu chỉ dành cho cơ sở dữ liệu demo.',
                'is_active' => $name !== 'Ban công',
            ]);
            $amenityIds[$name] = (int) $amenity->id;

            if ($name === 'Ban công') {
                $hiddenAmenityId = (int) $amenity->id;
            }
        }

        return [$amenityIds, $hiddenAmenityId];
    }

    /** @return array<int, int> */
    private function createCategories(): array
    {
        RoomCategory::query()->create([
            'name' => 'Căn hộ tiện nghi (Demo)',
            'description' => 'Danh mục chỉ dành cho cơ sở dữ liệu demo.',
            'is_active' => true,
        ]);
        RoomCategory::query()->create([
            'name' => 'Danh mục ẩn (Demo)',
            'description' => 'Danh mục ẩn chỉ dành cho cơ sở dữ liệu demo.',
            'is_active' => false,
        ]);

        $categoryIds = RoomCategory::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $categoryIds;
    }

    /**
     * @param  array<int, int>  $wardIds
     * @param  array<int, int>  $categoryIds
     * @param  array<string, int>  $amenityIds
     * @param  array<int, string>  $writtenPaths
     * @return array<string, Listing>
     */
    private function createListings(
        User $landlord,
        User $admin,
        array $wardIds,
        array $categoryIds,
        array $amenityIds,
        int $hiddenAmenityId,
        array &$writtenPaths,
    ): array {
        $specifications = [
            ['key' => 'available', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 0, 'days_ago' => 2, 'ward' => 0, 'amenities' => ['Wi-Fi', 'Máy lạnh', 'Ban công']],
            ['key' => 'rented', 'moderation' => 'APPROVED', 'occupancy' => 'RENTED', 'visibility' => 'VISIBLE', 'months_ago' => 0, 'days_ago' => 8, 'ward' => 1, 'amenities' => ['Wi-Fi', 'Nội thất']],
            ['key' => 'hidden', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'HIDDEN', 'months_ago' => 0, 'days_ago' => 12, 'ward' => 2, 'amenities' => ['Chỗ để xe']],
            ['key' => 'suspended', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'SUSPENDED', 'months_ago' => 0, 'days_ago' => 20, 'ward' => 3, 'amenities' => ['Máy giặt']],
            ['key' => 'expired', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 2, 'days_ago' => 0, 'ward' => 0, 'amenities' => ['Wi-Fi']],
            ['key' => 'pending', 'moderation' => 'PENDING', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 0, 'days_ago' => 1, 'ward' => 1, 'amenities' => ['Máy lạnh']],
            ['key' => 'rejected', 'moderation' => 'REJECTED', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 1, 'days_ago' => 0, 'ward' => 2, 'amenities' => ['Nội thất']],
            ['key' => 'available-alt', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 0, 'days_ago' => 4, 'ward' => 3, 'amenities' => ['Wi-Fi', 'Máy giặt', 'Chỗ để xe']],
            ['key' => 'chart-03', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'HIDDEN', 'months_ago' => 3, 'days_ago' => 0, 'ward' => 0, 'amenities' => ['Wi-Fi']],
            ['key' => 'chart-06', 'moderation' => 'APPROVED', 'occupancy' => 'RENTED', 'visibility' => 'VISIBLE', 'months_ago' => 6, 'days_ago' => 0, 'ward' => 1, 'amenities' => ['Nội thất']],
            ['key' => 'chart-09', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'HIDDEN', 'months_ago' => 9, 'days_ago' => 0, 'ward' => 2, 'amenities' => ['Chỗ để xe']],
            ['key' => 'chart-11', 'moderation' => 'APPROVED', 'occupancy' => 'AVAILABLE', 'visibility' => 'VISIBLE', 'months_ago' => 11, 'days_ago' => 0, 'ward' => 3, 'amenities' => ['Máy lạnh']],
        ];
        $listings = [];
        $feeTypeId = (int) DB::table('fee_types')->where('code', 'ELECTRICITY')->value('id');
        $feeUnitId = (int) DB::table('fee_units')->where('code', 'PER_KWH')->value('id');

        foreach ($specifications as $index => $specification) {
            $createdAt = $this->listingCreatedAt($specification['months_ago'], $specification['days_ago']);
            $reviewedAt = $specification['moderation'] === 'PENDING' ? null : $createdAt->addHours(3);
            $expiresAt = $specification['moderation'] === 'APPROVED' ? $reviewedAt?->copy()->addDays(30) : null;
            $listing = new Listing;
            $listing->forceFill([
                'landlord_id' => $landlord->id,
                'category_id' => $categoryIds[$index % count($categoryIds)],
                'current_moderation_id' => null,
                'title' => 'Phòng demo '.str_replace('-', ' ', $specification['key']),
                'description' => 'Hình minh họa và thông tin mẫu để kiểm thử quy trình RoomRental V1.',
                'monthly_rent' => 3500000 + ($index * 250000),
                'deposit_amount' => 3500000,
                'area_m2' => 20 + ($index * 1.5),
                'max_occupants' => 2,
                'bedroom_count' => 1,
                'bathroom_count' => 1,
                'gender_requirement' => 'ANY',
                'ward_id' => $wardIds[$specification['ward']],
                'street_address' => '12 Đường demo '.($specification['ward'] + 1),
                'latitude' => null,
                'longitude' => null,
                'occupancy_status' => $specification['occupancy'],
                'visibility_status' => $specification['visibility'],
                'expires_at' => $expiresAt,
                'deleted_at' => null,
                'view_count' => 12 + ($index * 37),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $moderation = $listing->moderations()->create([
                'version_no' => 1,
                'status' => $specification['moderation'],
                'reviewed_by' => $reviewedAt === null ? null : $admin->id,
                'rejection_reason' => $specification['moderation'] === 'REJECTED' ? 'Thông tin mẫu cần được bổ sung.' : null,
                'submitted_at' => $createdAt,
                'reviewed_at' => $reviewedAt,
            ]);
            $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
            $listing->amenities()->sync(array_map(
                fn (string $name): int => $amenityIds[$name],
                $specification['amenities'],
            ));

            if ($specification['key'] === 'available') {
                $listing->amenities()->syncWithoutDetaching([$hiddenAmenityId]);
            }

            DB::table('listing_fees')->insert([
                'listing_id' => $listing->id,
                'fee_type_id' => $feeTypeId,
                'fee_unit_id' => $feeUnitId,
                'amount' => 3500,
                'note' => 'Đơn giá demo theo kWh.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->createListingImages($listing, $index, $writtenPaths);
            $listings[$specification['key']] = $listing->fresh(['currentModeration']);
        }

        return $listings;
    }

    private function listingCreatedAt(int $monthsAgo, int $daysAgo): CarbonImmutable
    {
        if ($monthsAgo > 0) {
            return CarbonImmutable::now('UTC')->startOfMonth()->subMonths($monthsAgo)->addDays(4)->addHours(9);
        }

        return CarbonImmutable::now('UTC')->subDays($daysAgo);
    }

    /** @param array<int, string> $writtenPaths */
    private function createListingImages(Listing $listing, int $index, array &$writtenPaths): void
    {
        $now = now();

        foreach ([0, 1, 2] as $imageIndex) {
            $path = "demo-fixtures/listings/{$listing->id}/demo-".($imageIndex + 1).'.png';
            $writtenPaths[] = $path;

            if (! Storage::disk('public')->put($path, $this->placeholderPng(($index + $imageIndex) % 3))) {
                throw new RuntimeException('A demo placeholder image could not be written.');
            }

            ListingImage::query()->create([
                'listing_id' => $listing->id,
                'image_url' => $path,
                'is_cover' => $imageIndex === 0,
                'display_order' => $imageIndex + 1,
                'created_at' => $now,
            ]);
        }
    }

    private function createAppointments(User $renter, Listing $listing): void
    {
        $localNow = CarbonImmutable::now(ViewingSlot::TIMEZONE);
        $utcNow = CarbonImmutable::now('UTC');
        $acceptedStart = $localNow->startOfDay()->addHours(22);
        $completedStart = $localNow->subDays(3)->setTime(10, 0);
        $cancelledStart = $localNow->addDays(5)->setTime(10, 0);
        $autoCancelledStart = $localNow->subDays(6)->setTime(10, 0);
        $appointments = [
            [
                'status' => 'PENDING',
                'start' => $localNow->addDays(3)->setTime(10, 0),
                'created_at' => $utcNow->subMinutes(10),
            ],
            [
                'status' => 'ACCEPTED',
                'start' => $acceptedStart,
                'created_at' => $acceptedStart->subDays(2)->utc(),
                'responded_at' => $acceptedStart->subDay()->utc(),
            ],
            [
                'status' => 'COMPLETED',
                'start' => $completedStart,
                'created_at' => $completedStart->subDays(2)->utc(),
                'responded_at' => $completedStart->subDay()->utc(),
                'completed_at' => $completedStart->addMinutes(45)->utc(),
            ],
            [
                'status' => 'CANCELLED',
                'start' => $cancelledStart,
                'created_at' => $localNow->subDays(3)->utc(),
                'cancelled_by' => $renter->id,
                'cancelled_at' => $localNow->subDay()->utc(),
                'cancellation_reason' => 'DEMO_RENTER_CANCELLED',
            ],
            [
                'status' => 'AUTO_CANCELLED',
                'start' => $autoCancelledStart,
                'created_at' => $autoCancelledStart->subDays(2)->utc(),
                'cancelled_by' => null,
                'cancelled_at' => $autoCancelledStart->addHour()->utc(),
                'cancellation_reason' => 'VIEWING_TIME_PASSED',
            ],
        ];

        foreach ($appointments as $attributes) {
            $start = $attributes['start'];
            $slot = ViewingSlot::query()->create([
                'listing_id' => $listing->id,
                'viewing_date' => $start->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'end_time' => $start->addMinutes(30)->format('H:i:s'),
                'status' => 'OPEN',
            ]);

            $appointment = new Appointment;
            $appointment->forceFill([
                'slot_id' => $slot->id,
                'renter_id' => $renter->id,
                'status' => $attributes['status'],
                'renter_note' => $attributes['status'] === 'PENDING' ? 'Lịch hẹn demo.' : null,
                'landlord_response' => $attributes['status'] === 'ACCEPTED' || $attributes['status'] === 'COMPLETED'
                    ? 'Đã xác nhận lịch hẹn demo.'
                    : null,
                'cancelled_by' => $attributes['cancelled_by'] ?? null,
                'cancelled_at' => $attributes['cancelled_at'] ?? null,
                'cancellation_reason' => $attributes['cancellation_reason'] ?? null,
                'responded_at' => $attributes['responded_at'] ?? null,
                'completed_at' => $attributes['completed_at'] ?? null,
                'created_at' => $attributes['created_at'],
                'updated_at' => $attributes['created_at'],
            ])->save();
        }
    }

    /** @param array{admin: User, landlord: User, renter: User} $actors
     * @param  array<string, Listing>  $listings
     */
    private function createReports(array $actors, array $listings): void
    {
        $now = CarbonImmutable::now('UTC');
        $reasons = DB::table('report_reasons')->pluck('id', 'code');
        $fixtures = [
            [
                'listing' => 'available',
                'reason' => 'WRONG_ADDRESS',
                'status' => 'PENDING',
                'created_at' => $now->subHours(2),
            ],
            [
                'listing' => 'rented',
                'reason' => 'WRONG_PRICE',
                'status' => 'DISMISSED',
                'created_at' => $now->subDays(7),
                'handled_at' => $now->subDays(6),
                'resolution_reason' => 'Thông tin trong báo cáo demo không được xác nhận.',
            ],
            [
                'listing' => 'available-alt',
                'reason' => 'FRAUD',
                'status' => 'RESOLVED',
                'created_at' => $now->subDays(3),
                'handled_at' => $now->subDays(2),
                'action' => 'WARNING',
                'action_reason' => 'Cảnh báo mẫu cho dữ liệu demo.',
            ],
            [
                'listing' => 'suspended',
                'reason' => 'INAPPROPRIATE_CONTENT',
                'status' => 'RESOLVED',
                'created_at' => $now->subDays(19),
                'handled_at' => $now->subDays(18),
                'action' => 'SUSPEND_LISTING',
                'action_reason' => 'Tạm ẩn mẫu để minh họa xử lý báo cáo.',
            ],
        ];

        foreach ($fixtures as $fixture) {
            $createdAt = $fixture['created_at'];
            $handledAt = $fixture['handled_at'] ?? null;
            $report = new Report;
            $report->forceFill([
                'reporter_id' => $actors['renter']->id,
                'listing_id' => $listings[$fixture['listing']]->id,
                'reason_id' => $reasons[$fixture['reason']],
                'description' => 'Báo cáo minh họa cho quy trình demo.',
                'status' => $fixture['status'],
                'handled_by' => $handledAt === null ? null : $actors['admin']->id,
                'resolution_reason' => $fixture['resolution_reason'] ?? null,
                'handled_at' => $handledAt,
                'created_at' => $createdAt,
                'updated_at' => $handledAt ?? $createdAt,
            ])->save();

            if ($handledAt === null) {
                continue;
            }

            $action = $fixture['action'] ?? null;
            if ($action !== null) {
                EnforcementAction::query()->create([
                    'report_id' => $report->id,
                    'admin_id' => $actors['admin']->id,
                    'action_type' => $action,
                    'target_user_id' => $action === 'WARNING' ? $actors['landlord']->id : null,
                    'target_listing_id' => $action === 'SUSPEND_LISTING' ? $listings[$fixture['listing']]->id : null,
                    'reason' => $fixture['action_reason'],
                    'created_at' => $handledAt,
                ]);
            }

            AuditLog::query()->create([
                'actor_user_id' => $actors['admin']->id,
                'action' => match ($action) {
                    'WARNING' => 'report.resolved.warning',
                    'SUSPEND_LISTING' => 'report.resolved.suspend_listing',
                    default => 'report.dismissed',
                },
                'entity_type' => Report::class,
                'entity_id' => $report->id,
                'created_at' => $handledAt,
            ]);
        }
    }

    /** @return array<int, int> */
    private function placeholderPixel(int $x, int $y, int $variant): array
    {
        $palettes = [
            ['sky' => [222, 238, 239], 'wall' => [250, 242, 223], 'roof' => [190, 111, 90], 'window' => [113, 171, 181], 'door' => [111, 92, 77], 'ground' => [183, 204, 167], 'label' => [55, 75, 81]],
            ['sky' => [231, 232, 246], 'wall' => [249, 239, 226], 'roof' => [117, 133, 174], 'window' => [111, 163, 174], 'door' => [122, 96, 78], 'ground' => [197, 211, 179], 'label' => [55, 68, 91]],
            ['sky' => [226, 240, 229], 'wall' => [250, 243, 225], 'roof' => [183, 139, 82], 'window' => [101, 164, 158], 'door' => [105, 89, 75], 'ground' => [184, 207, 170], 'label' => [57, 78, 67]],
        ];
        $palette = $palettes[$variant];
        $color = $palette['sky'];

        if ($y >= 202) {
            $color = $palette['ground'];
        }
        if ($y >= 88 && $y < 202 && $x >= 55 && $x <= 265) {
            $color = $palette['wall'];
        }
        if ($y >= 48 && $y < 90 && abs($x - 160) <= ($y - 46) * 2.5) {
            $color = $palette['roof'];
        }
        if ($y >= 112 && $y <= 157 && (($x >= 78 && $x <= 126) || ($x >= 194 && $x <= 242))) {
            $color = $palette['window'];
        }
        if ($y >= 149 && $y < 202 && $x >= 140 && $x <= 180) {
            $color = $palette['door'];
        }
        if ($y >= 218) {
            $color = $palette['label'];
        }

        $glyphs = [
            'D' => ['11110', '10001', '10001', '10001', '10001', '10001', '11110'],
            'E' => ['11111', '10000', '10000', '11110', '10000', '10000', '11111'],
            'M' => ['10001', '11011', '10101', '10101', '10001', '10001', '10001'],
            'O' => ['01110', '10001', '10001', '10001', '10001', '10001', '01110'],
        ];
        $textX = $x - 125;
        $textY = $y - 220;

        if ($textX >= 0 && $textY >= 0 && $textY < 14) {
            $characterIndex = intdiv($textX, 12);
            $characterX = intdiv($textX % 12, 2);
            $characterY = intdiv($textY, 2);
            $character = ['D', 'E', 'M', 'O'][$characterIndex] ?? null;

            if ($character !== null && $characterX < 5 && $glyphs[$character][$characterY][$characterX] === '1') {
                $color = [255, 255, 255];
            }
        }

        return $color;
    }

    private function placeholderPng(int $variant): string
    {
        if (isset($this->placeholderImages[$variant])) {
            return $this->placeholderImages[$variant];
        }

        $width = 320;
        $height = 240;
        $pixels = '';

        for ($y = 0; $y < $height; $y++) {
            $pixels .= "\0";

            for ($x = 0; $x < $width; $x++) {
                [$red, $green, $blue] = $this->placeholderPixel($x, $y, $variant);
                $pixels .= chr($red).chr($green).chr($blue);
            }
        }

        return $this->placeholderImages[$variant] = "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$this->pngChunk('IDAT', gzcompress($pixels, 9))
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
