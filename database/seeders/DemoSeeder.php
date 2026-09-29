<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\EnforcementAction;
use App\Models\Listing;
use App\Models\Province;
use App\Models\Report;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Models\Ward;
use App\Queries\ListingSearchQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
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

    /** @var array<int, array<string, mixed>> */
    private array $listingImageRows = [];

    /** These are illustrative legacy/demo fixtures, not official geography. */
    private const DEMO_LOCATION_FIXTURE = [
        ['name' => 'TP.HCM', 'districts' => ['Quận mẫu 01', 'Quận mẫu 02', 'Quận mẫu 03']],
        ['name' => 'Hà Nội', 'districts' => ['Quận mẫu 04', 'Quận mẫu 05', 'Quận mẫu 06']],
        ['name' => 'Đà Nẵng', 'districts' => ['Quận mẫu 07', 'Quận mẫu 08', 'Quận mẫu 09']],
        ['name' => 'Cần Thơ', 'districts' => ['Quận mẫu 10', 'Quận mẫu 11', 'Quận mẫu 12']],
    ];

    private const STREET_NAMES = [
        'Đường Hoa Giấy',
        'Đường Gió Mát',
        'Đường Vườn Xanh',
        'Đường số 3',
        'Đường Ánh Dương',
        'Đường Mộc Lan',
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
        $this->listingImageRows = [];
        $originalTimeZone = DB::selectOne('SELECT @@session.time_zone AS time_zone')->time_zone;
        DB::statement("SET SESSION time_zone = '+00:00'");

        try {
            DB::transaction(function () use (&$writtenPaths): void {
                $roleIds = DB::table('roles')
                    ->whereIn('code', ['ADMIN', 'LANDLORD', 'RENTER'])
                    ->pluck('id', 'code')
                    ->all();

                if (count($roleIds) !== 3 || RoomCategory::query()->count() < 6
                    || ! DB::table('fee_types')->where('code', 'ELECTRICITY')->exists()
                    || ! DB::table('fee_units')->where('code', 'PER_KWH')->exists()
                    || DB::table('report_reasons')->count() !== 5) {
                    throw new RuntimeException('Run the production-safe DatabaseSeeder before DemoSeeder.');
                }

                $actors = $this->createActors($roleIds);
                $locations = $this->createLocations();
                $catalog = $this->createDemoCatalog();
                $specifications = $this->buildListingSpecifications();
                $this->assignCategories($specifications, $catalog['categoryIds'], $catalog['hiddenCategoryId']);
                $this->assignHistoryMonths($specifications);
                $this->assignLocations($specifications, $locations);
                $this->assignListingDetails($specifications);

                $listings = $this->createListings(
                    $specifications,
                    $actors,
                    $catalog,
                    $locations,
                    $writtenPaths,
                );
                DB::table('listing_images')->insert($this->listingImageRows);

                $this->createFees($listings);
                $publicListings = app(ListingSearchQuery::class)->build()->orderBy('listings.id')->get();

                if ($publicListings->count() !== 80) {
                    throw new RuntimeException('Realistic Demo V2 must create exactly 80 publicly eligible listings.');
                }

                $this->createReports($actors, $listings);
                $this->createLockedAccountHistory($actors);
                $this->createFavorites($actors, $listings, $publicListings);
                $this->createViewingSlotsAndAppointments($actors, $listings, $publicListings);
                $this->assertFinalCounts();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($writtenPaths);

            throw $exception;
        } finally {
            DB::statement('SET SESSION time_zone = ?', [$originalTimeZone]);
        }

        $this->command?->info('Realistic Demo V2 created: 120 users, 135 listings, 320 slots, 180 appointments, 60 reports, and 432 local images.');
    }

    private function hasExistingApplicationData(): bool
    {
        return DB::table('users')->exists()
            || DB::table('listings')->exists()
            || DB::table('provinces')->exists()
            || DB::table('amenities')->exists();
    }

    /** @param array<string, int> $roleIds
     * @return array<string, mixed>
     */
    private function createActors(array $roleIds): array
    {
        $now = CarbonImmutable::now('UTC');
        $specifications = [
            ['key' => 'renter', 'email' => 'demo-renter@roomrental.test', 'phone' => '+840200000001', 'name' => 'Nguyễn An Nhiên', 'roles' => ['RENTER'], 'locked' => false],
            ['key' => 'landlord', 'email' => 'demo-landlord@roomrental.test', 'phone' => '+840200000002', 'name' => 'Trần Minh Khang', 'roles' => ['RENTER', 'LANDLORD'], 'locked' => false],
            ['key' => 'second_landlord', 'email' => 'demo-landlord-second@roomrental.test', 'phone' => '+840200000004', 'name' => 'Phạm Gia Hân', 'roles' => ['RENTER', 'LANDLORD'], 'locked' => false],
            ['key' => 'heavy_landlord', 'email' => 'demo-landlord-heavy@roomrental.test', 'phone' => '+840200000005', 'name' => 'Võ Đức Thành', 'roles' => ['RENTER', 'LANDLORD'], 'locked' => false],
            ['key' => 'locked_landlord', 'email' => 'demo-landlord-locked@roomrental.test', 'phone' => '+840200000006', 'name' => 'Bùi Thu Hà', 'roles' => ['RENTER', 'LANDLORD'], 'locked' => true],
            ['key' => 'admin', 'email' => 'demo-admin@roomrental.test', 'phone' => '+840200000003', 'name' => 'Lê Bảo Châu', 'roles' => ['ADMIN'], 'locked' => false],
            ['key' => 'admin_two', 'email' => 'demo-admin-02@roomrental.test', 'phone' => '+840200000007', 'name' => 'Đặng Minh Tâm', 'roles' => ['ADMIN'], 'locked' => false],
            ['key' => 'admin_three', 'email' => 'demo-admin-03@roomrental.test', 'phone' => '+840200000008', 'name' => 'Hoàng Ngọc Mai', 'roles' => ['ADMIN'], 'locked' => false],
        ];

        for ($index = 0; $index < 96; $index++) {
            $number = $index + 1;
            $specifications[] = [
                'key' => 'renter_'.$number,
                'email' => sprintf('nguoi.thue.%03d@roomrental.test', $number),
                'phone' => sprintf('+8400%08d', $number),
                'name' => $this->syntheticName($index),
                'roles' => ['RENTER'],
                'locked' => in_array($number, [7, 31, 61], true),
            ];
        }

        for ($index = 0; $index < 16; $index++) {
            $number = $index + 1;
            $specifications[] = [
                'key' => 'landlord_'.$number,
                'email' => sprintf('chu.tro.%02d@roomrental.test', $number),
                'phone' => sprintf('+8401%08d', $number),
                'name' => $this->syntheticName($index + 96),
                'roles' => ['RENTER', 'LANDLORD'],
                'locked' => false,
            ];
        }

        $users = [];
        $renterOnly = [];
        $landlords = [];
        $activeRenterOnly = [];

        foreach ($specifications as $index => $specification) {
            $createdAt = $now->subDays(180 - min($index, 120));
            $user = new User;
            $user->forceFill([
                'email' => $specification['email'],
                'phone' => $specification['phone'],
                'password_hash' => Hash::make(self::DEMO_PASSWORD),
                'account_status' => $specification['locked'] ? 'LOCKED' : 'ACTIVE',
                'failed_login_count' => 0,
                'login_blocked_until' => null,
                'must_change_password' => false,
                'last_login_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            DB::table('user_profiles')->insert([
                'user_id' => $user->id,
                'full_name' => $specification['name'],
                'avatar_url' => null,
                'contact_address' => in_array('LANDLORD', $specification['roles'], true)
                    ? 'Thông tin liên hệ chỉ dùng cho cơ sở dữ liệu demo.'
                    : null,
                'zalo_number' => in_array('LANDLORD', $specification['roles'], true)
                    ? $specification['phone']
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($specification['roles'] as $roleCode) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleIds[$roleCode],
                    'assigned_by' => null,
                    'assigned_at' => $createdAt,
                ]);
            }

            $users[$specification['key']] = $user;

            if ($specification['roles'] === ['RENTER']) {
                $renterOnly[] = $user;
                if (! $specification['locked']) {
                    $activeRenterOnly[] = $user;
                }
            }

            if (in_array('LANDLORD', $specification['roles'], true)) {
                $landlords[] = $user;
            }
        }

        return [
            'users' => $users,
            'renter' => $users['renter'],
            'landlord' => $users['landlord'],
            'second_landlord' => $users['second_landlord'],
            'heavy_landlord' => $users['heavy_landlord'],
            'locked_landlord' => $users['locked_landlord'],
            'admin' => $users['admin'],
            'admins' => [$users['admin'], $users['admin_two'], $users['admin_three']],
            'landlords' => $landlords,
            'renter_only' => $renterOnly,
            'active_renter_only' => $activeRenterOnly,
        ];
    }

    private function syntheticName(int $index): string
    {
        $familyNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương'];
        $middleNames = ['Minh', 'Gia', 'Thanh', 'Bảo', 'Ngọc', 'Quốc', 'Thu', 'Anh', 'Hải', 'Khánh'];
        $givenNames = ['An', 'Bình', 'Châu', 'Duy', 'Hà', 'Hân', 'Khang', 'Linh', 'Mai', 'Nam', 'Phúc', 'Vy'];

        return $familyNames[($index * 7) % count($familyNames)]
            .' '.$middleNames[intdiv($index, count($familyNames))]
            .' '.$givenNames[($index * 5) % count($givenNames)];
    }

    /** @return array{wards: array<int, array<int, int>>, regionNames: array<int, string>} */
    private function createLocations(): array
    {
        $wardIds = [];
        $regionNames = [];

        foreach (self::DEMO_LOCATION_FIXTURE as $regionIndex => $regionData) {
            $provinceCode = sprintf('DEMO-P%02d', $regionIndex + 1);
            $province = Province::query()->create([
                'code' => $provinceCode,
                'name' => $regionData['name'],
                'is_active' => true,
            ]);
            $regionNames[$regionIndex] = $regionData['name'];

            foreach ($regionData['districts'] as $districtIndex => $districtName) {
                $districtCode = sprintf('%s-D%02d', $provinceCode, $districtIndex + 1);
                $district = District::query()->create([
                    'province_id' => $province->id,
                    'code' => $districtCode,
                    'name' => $districtName,
                    'is_active' => true,
                ]);

                for ($wardIndex = 0; $wardIndex < 4; $wardIndex++) {
                    $ward = Ward::query()->create([
                        'district_id' => $district->id,
                        'code' => sprintf('%s-W%02d', $districtCode, $wardIndex + 1),
                        'name' => sprintf('Phường mẫu %02d-%02d', $districtIndex + 1, $wardIndex + 1),
                        'is_active' => true,
                    ]);
                    $wardIds[$regionIndex][] = (int) $ward->id;
                }
            }
        }

        return ['wards' => $wardIds, 'regionNames' => $regionNames];
    }

    /** @return array{amenityIds: array<string, int>, hiddenAmenityId: int, categoryIds: array<string, int>, hiddenCategoryId: int} */
    private function createDemoCatalog(): array
    {
        $amenityIds = [];

        foreach (['Wi-Fi', 'Máy lạnh', 'Máy giặt', 'Chỗ để xe', 'Nội thất', 'Ban công'] as $name) {
            $amenity = Amenity::query()->create([
                'name' => $name,
                'description' => 'Tiện nghi mẫu chỉ dành cho cơ sở dữ liệu demo.',
                'is_active' => $name !== 'Ban công',
            ]);
            $amenityIds[$name] = (int) $amenity->id;
        }

        $categoryIds = RoomCategory::query()
            ->where('is_active', true)
            ->whereIn('name', ['Phòng trọ', 'Căn hộ mini', 'Studio', 'Chung cư', 'Nhà nguyên căn', 'Ở ghép'])
            ->pluck('id', 'name')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $hiddenCategory = RoomCategory::query()->firstOrCreate(
            ['name' => 'Danh mục ẩn (Demo)'],
            ['description' => 'Danh mục lịch sử chỉ dành cho dữ liệu demo.', 'is_active' => false],
        );
        $hiddenCategory->forceFill(['is_active' => false])->save();

        if (count($categoryIds) !== 6) {
            throw new RuntimeException('The six approved room categories are required before DemoSeeder.');
        }

        return [
            'amenityIds' => $amenityIds,
            'hiddenAmenityId' => $amenityIds['Ban công'],
            'categoryIds' => $categoryIds,
            'hiddenCategoryId' => (int) $hiddenCategory->id,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildListingSpecifications(): array
    {
        $specifications = [];
        $add = static function (
            string $key,
            string $moderation,
            string $occupancy,
            string $visibility,
            ?string $owner = null,
            bool $deleted = false,
            string $expiry = 'renewed',
            bool $recent = false,
            bool $public = false,
        ) use (&$specifications): void {
            $specifications[] = compact('key', 'moderation', 'occupancy', 'visibility', 'owner', 'deleted', 'expiry', 'recent', 'public');
        };

        // Main demo landlord: 9 public, 12 available, 3 rented; 15 total.
        $add('public-bookable-studio', 'APPROVED', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'renewed', false, true);
        $add('pending-moderation', 'PENDING', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'none', true);
        $add('pending-report-target', 'APPROVED', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'renewed', false, true);
        $add('expired-renewable', 'APPROVED', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'expired');
        $add('hidden-amenity-reference', 'APPROVED', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'renewed', false, true);
        for ($index = 5; $index <= 10; $index++) {
            $add('main-public-'.$index, 'APPROVED', 'AVAILABLE', 'VISIBLE', 'landlord', false, 'renewed', false, true);
        }
        $add('main-hidden-available', 'APPROVED', 'AVAILABLE', 'HIDDEN', 'landlord');
        $add('main-rented-expired', 'APPROVED', 'RENTED', 'VISIBLE', 'landlord', false, 'expired');
        $add('main-rented-current', 'APPROVED', 'RENTED', 'VISIBLE', 'landlord');
        $add('main-pending-rented', 'PENDING', 'RENTED', 'HIDDEN', 'landlord', false, 'none');

        for ($index = 1; $index <= 71; $index++) {
            $add(sprintf('market-public-%03d', $index), 'APPROVED', 'AVAILABLE', 'VISIBLE', null, false, 'renewed', false, true);
        }

        // Approved non-public inventory: 15 rented, 3 expired available,
        // one suspended, one locked-landlord available, and two deleted.
        $add('locked-landlord-rented', 'APPROVED', 'RENTED', 'VISIBLE', 'locked_landlord');
        for ($index = 1; $index <= 13; $index++) {
            $add(sprintf('market-rented-expired-%02d', $index), 'APPROVED', 'RENTED', 'VISIBLE', null, false, 'expired');
        }
        $add('market-rented-current', 'APPROVED', 'RENTED', 'VISIBLE', null);
        for ($index = 1; $index <= 3; $index++) {
            $add(sprintf('market-expired-available-%02d', $index), 'APPROVED', 'AVAILABLE', 'VISIBLE', null, false, 'expired');
        }
        $add('suspended-listing', 'APPROVED', 'AVAILABLE', 'SUSPENDED', 'second_landlord');
        $add('locked-landlord-visible', 'APPROVED', 'AVAILABLE', 'VISIBLE', 'locked_landlord');
        $add('deleted-approved-rented', 'APPROVED', 'RENTED', 'VISIBLE', null, true, 'renewed');
        $add('deleted-approved-hidden', 'APPROVED', 'AVAILABLE', 'HIDDEN', null, true, 'renewed');

        // Remaining non-deleted pending/rejected matrix: visible 5, hidden 14,
        // suspended 7; 3 rented across these 26 records.
        for ($index = 1; $index <= 2; $index++) {
            $add(sprintf('pending-visible-%02d', $index), 'PENDING', 'AVAILABLE', 'VISIBLE', null, false, 'none');
        }
        for ($index = 1; $index <= 8; $index++) {
            $add(sprintf('pending-hidden-available-%02d', $index), 'PENDING', 'AVAILABLE', 'HIDDEN', null, false, 'none');
        }
        $add('pending-hidden-rented', 'PENDING', 'RENTED', 'HIDDEN', null, false, 'none');
        for ($index = 1; $index <= 5; $index++) {
            $add(sprintf('pending-suspended-available-%02d', $index), 'PENDING', 'AVAILABLE', 'SUSPENDED', null, false, 'none');
        }
        $add('pending-suspended-rented', 'PENDING', 'RENTED', 'SUSPENDED', null, false, 'none');

        for ($index = 1; $index <= 3; $index++) {
            $add(sprintf('rejected-visible-%02d', $index), 'REJECTED', 'AVAILABLE', 'VISIBLE', null, false, 'none');
        }
        for ($index = 1; $index <= 4; $index++) {
            $add(sprintf('rejected-hidden-%02d', $index), 'REJECTED', 'AVAILABLE', 'HIDDEN', null, false, 'none');
        }
        $add('hidden-category-reference', 'REJECTED', 'AVAILABLE', 'HIDDEN', 'second_landlord', false, 'none');
        $add('rejected-suspended-rented', 'REJECTED', 'RENTED', 'SUSPENDED', null, false, 'none');
        $add('deleted-pending-visible', 'PENDING', 'AVAILABLE', 'VISIBLE', null, true, 'none');

        if (count($specifications) !== 135) {
            throw new RuntimeException('The deterministic listing state matrix must contain exactly 135 rows.');
        }

        return $specifications;
    }

    /** @param array<int, array<string, mixed>> $specifications
     * @param  array<string, int>  $categoryIds
     */
    private function assignCategories(array &$specifications, array $categoryIds, int $hiddenCategoryId): void
    {
        $counts = [
            'Phòng trọ' => 53,
            'Căn hộ mini' => 27,
            'Studio' => 21,
            'Chung cư' => 15,
            'Nhà nguyên căn' => 10,
            'Ở ghép' => 8,
        ];
        $forced = [
            'public-bookable-studio' => 'Studio',
            'expired-renewable' => 'Căn hộ mini',
            'pending-moderation' => 'Phòng trọ',
            'pending-report-target' => 'Phòng trọ',
            'suspended-listing' => 'Phòng trọ',
            'hidden-amenity-reference' => 'Studio',
        ];

        foreach ($forced as $category) {
            $counts[$category]--;
        }

        $categoryPool = [];
        $assignedCounts = array_fill_keys(array_keys($counts), 0);
        while (count($categoryPool) < array_sum($counts)) {
            $nextCategory = null;
            $nextPosition = INF;
            foreach ($counts as $name => $count) {
                if ($assignedCounts[$name] >= $count) {
                    continue;
                }

                $position = ($assignedCounts[$name] + 1) / $count;
                if ($position < $nextPosition) {
                    $nextCategory = $name;
                    $nextPosition = $position;
                }
            }
            $categoryPool[] = $nextCategory;
            $assignedCounts[$nextCategory]++;
        }

        $poolIndex = 0;
        foreach ($specifications as &$specification) {
            if ($specification['key'] === 'hidden-category-reference') {
                $specification['category_id'] = $hiddenCategoryId;
                $specification['category_name'] = 'Danh mục ẩn (Demo)';

                continue;
            }

            $categoryName = $forced[$specification['key']] ?? $categoryPool[$poolIndex++];
            $specification['category_id'] = $categoryIds[$categoryName];
            $specification['category_name'] = $categoryName;
        }
        unset($specification);
    }

    /** @param array<int, array<string, mixed>> $specifications */
    private function assignHistoryMonths(array &$specifications): void
    {
        $bucketCounts = [11 => 4, 10 => 5, 9 => 6, 8 => 7, 7 => 8, 6 => 9, 5 => 10, 4 => 11, 3 => 13, 2 => 16, 1 => 20, 0 => 26];
        $remaining = $bucketCounts;
        $assigned = [];

        foreach ($specifications as $index => $specification) {
            if ($specification['recent']) {
                $assigned[$index] = 0;
                $remaining[0]--;
            }
        }

        $recentStateFixtures = [
            'main-rented-current',
            'market-rented-current',
            'main-hidden-available',
            'suspended-listing',
            'locked-landlord-visible',
            'deleted-approved-rented',
            'deleted-approved-hidden',
            'deleted-pending-visible',
        ];
        foreach ($specifications as $index => $specification) {
            if (in_array($specification['key'], $recentStateFixtures, true)) {
                $assigned[$index] = 1;
                $remaining[1]--;
            }
        }

        foreach ($specifications as $index => $specification) {
            if ($specification['expiry'] !== 'expired') {
                continue;
            }

            foreach (range(11, 2) as $monthOffset) {
                if ($remaining[$monthOffset] > 0) {
                    $assigned[$index] = $monthOffset;
                    $remaining[$monthOffset]--;
                    break;
                }
            }
        }

        foreach (range(11, 0) as $monthOffset) {
            while ($remaining[$monthOffset] > 0) {
                foreach ($specifications as $index => $specification) {
                    if (! array_key_exists($index, $assigned)) {
                        $assigned[$index] = $monthOffset;
                        $remaining[$monthOffset]--;
                        break;
                    }
                }
            }
        }

        $now = CarbonImmutable::now('UTC');
        foreach ($specifications as $index => &$specification) {
            $specification['months_ago'] = $assigned[$index];
            $specification['created_at'] = $this->listingCreatedAt($assigned[$index], $index, $now);
        }
        unset($specification);
    }

    private function listingCreatedAt(int $monthsAgo, int $index, CarbonImmutable $now): CarbonImmutable
    {
        $monthStart = $now->startOfMonth()->subMonths($monthsAgo);
        $candidate = $monthStart->addDays(2 + ($index % 15))->setTime(9 + ($index % 8), ($index % 2) * 30);

        return $candidate->greaterThan($now) ? $now : $candidate;
    }

    /** @param array<int, array<string, mixed>> $specifications
     * @param  array{wards: array<int, array<int, int>>, regionNames: array<int, string>}  $locations
     */
    private function assignLocations(array &$specifications, array $locations): void
    {
        $regionTargets = [54, 40, 25, 16];
        $regionIndex = 0;
        $regionCount = 0;

        foreach ($specifications as $index => &$specification) {
            while ($regionCount >= $regionTargets[$regionIndex]) {
                $regionCount = 0;
                $regionIndex++;
            }

            $localIndex = $regionCount++;
            $wardPosition = $index < 12 ? intdiv($index, 2) : ($localIndex * 5) % 12;
            $specification['region_index'] = $regionIndex;
            $specification['region_name'] = $locations['regionNames'][$regionIndex];
            $specification['ward_id'] = $locations['wards'][$regionIndex][$wardPosition];
            $specification['street_address'] = $index < 12
                ? $this->pairedMainAddress(intdiv($index, 2))
                : $this->generatedAddress($index, $wardPosition);
            $specification['room_label'] = sprintf('R%03d', $index + 1);
        }
        unset($specification);
    }

    private function pairedMainAddress(int $pairIndex): string
    {
        $forms = ['12/3', '45A', '88/2', '19B', '72/5', '31A'];

        return $forms[$pairIndex].' '.self::STREET_NAMES[$pairIndex];
    }

    private function generatedAddress(int $index, int $wardPosition): string
    {
        $number = 10 + (($index * 17) % 180);
        $street = self::STREET_NAMES[($index * 7 + $wardPosition) % count(self::STREET_NAMES)];

        return match ($index % 3) {
            0 => $number.'A '.$street,
            1 => $number.'/'.(1 + ($index % 9)).' '.$street,
            default => $number.' '.$street,
        };
    }

    /** @param array<int, array<string, mixed>> $specifications */
    private function assignListingDetails(array &$specifications): void
    {
        $ranges = [
            'Ở ghép' => ['area' => [12, 22], 'rent' => [1200000, 2800000]],
            'Phòng trọ' => ['area' => [16, 32], 'rent' => [2200000, 5200000]],
            'Căn hộ mini' => ['area' => [25, 45], 'rent' => [4500000, 8500000]],
            'Studio' => ['area' => [22, 42], 'rent' => [5000000, 9500000]],
            'Chung cư' => ['area' => [45, 85], 'rent' => [7500000, 16000000]],
            'Nhà nguyên căn' => ['area' => [60, 130], 'rent' => [9000000, 22000000]],
        ];
        $categoryRanks = ['Ở ghép' => 0, 'Phòng trọ' => 1, 'Căn hộ mini' => 2, 'Studio' => 2, 'Chung cư' => 3, 'Nhà nguyên căn' => 4];
        $categoryCounts = array_count_values(array_column($specifications, 'category_name'));
        $areaCategoryCounts = $categoryCounts;
        $areaCategoryCounts['Phòng trọ'] += $categoryCounts['Danh mục ẩn (Demo)'] ?? 0;
        $areaOrdinals = [];

        foreach ($specifications as &$specification) {
            $category = $specification['category_name'] === 'Danh mục ẩn (Demo)'
                ? 'Phòng trọ'
                : $specification['category_name'];
            $ordinal = $areaOrdinals[$category] ?? 0;
            $areaOrdinals[$category] = $ordinal + 1;
            $categoryCount = $areaCategoryCounts[$category];
            $rank = $categoryCount > 1 ? $ordinal / ($categoryCount - 1) : 0;
            $specification['category_rank'] = $categoryRanks[$category];
            $specification['area_ratio'] = $rank;
            $specification['area_m2'] = round($ranges[$category]['area'][0]
                + ($ranges[$category]['area'][1] - $ranges[$category]['area'][0]) * $rank, 2);
        }
        unset($specification);

        $amenityTargets = ['Wi-Fi' => 108, 'Chỗ để xe' => 97, 'Máy lạnh' => 65, 'Nội thất' => 54, 'Máy giặt' => 47];
        $amenitiesByIndex = array_fill(0, count($specifications), []);

        foreach ($amenityTargets as $amenity => $target) {
            $scores = [];
            foreach ($specifications as $index => $specification) {
                $bias = in_array($amenity, ['Máy lạnh', 'Nội thất', 'Máy giặt'], true)
                    ? $specification['category_rank'] * 9 + $specification['area_ratio'] * 12
                    : $specification['category_rank'] * 2 + $specification['area_ratio'] * 3;
                $scores[$index] = (($index * 47) % count($specifications)) - $bias;
            }
            asort($scores, SORT_NUMERIC);
            $selected = array_slice(array_keys($scores), 0, $target);
            if ($amenity === 'Máy lạnh' || $amenity === 'Nội thất') {
                $storyIndex = array_search('public-bookable-studio', array_column($specifications, 'key'), true);
                if (! in_array($storyIndex, $selected, true)) {
                    array_pop($selected);
                    $selected[] = $storyIndex;
                }
            }
            foreach ($selected as $index) {
                $amenitiesByIndex[$index][] = $amenity;
            }
        }

        $hiddenAmenityIndex = array_search('hidden-amenity-reference', array_column($specifications, 'key'), true);
        $amenitiesByIndex[$hiddenAmenityIndex][] = 'Ban công';
        $regionPremium = [0.06, 0.045, 0.02, 0.0];
        $now = CarbonImmutable::now('UTC');

        foreach ($specifications as $index => &$specification) {
            $category = $specification['category_name'] === 'Danh mục ẩn (Demo)'
                ? 'Phòng trọ'
                : $specification['category_name'];
            $specification['amenities'] = $amenitiesByIndex[$index];
            $amenityCount = count(array_filter($specification['amenities'], fn ($name) => $name !== 'Ban công'));
            $furnishingBonus = in_array('Nội thất', $specification['amenities'], true) ? 0.035 : 0;
            $priceFraction = min(0.94, 0.08
                + 0.72 * $specification['area_ratio']
                + $regionPremium[$specification['region_index']]
                + $amenityCount * 0.006
                + $furnishingBonus);
            $rentRange = $ranges[$category]['rent'];
            $rawRent = $rentRange[0] + ($rentRange[1] - $rentRange[0]) * $priceFraction;
            $specification['monthly_rent'] = floor($rawRent / 50000) * 50000;
            $specification['deposit_amount'] = $specification['monthly_rent']
                * ($category === 'Ở ghép' ? 0.5 : ($category === 'Nhà nguyên căn' ? 1.5 : 1));
            $specification['bedroom_count'] = match ($category) {
                'Căn hộ mini' => 1,
                'Chung cư' => max(1, min(3, (int) floor($specification['area_m2'] / 35))),
                'Nhà nguyên căn' => max(2, min(4, (int) floor($specification['area_m2'] / 30))),
                default => 0,
            };
            $specification['bathroom_count'] = $specification['area_m2'] >= 60 ? 2 : 1;
            $specification['max_occupants'] = match ($category) {
                'Ở ghép' => 2,
                'Phòng trọ', 'Căn hộ mini', 'Studio' => 2,
                'Chung cư' => max(2, min(4, (int) floor($specification['area_m2'] / 25))),
                'Nhà nguyên căn' => max(3, min(6, (int) floor($specification['area_m2'] / 22))),
                default => 2,
            };
            $specification['gender_requirement'] = 'ANY';
            $specification['views'] = match (true) {
                $index % 29 === 0 => 600 + (($index * 37) % 601),
                $index % 5 === 0 => 100 + (($index * 29) % 251),
                $index % 17 === 0 => 0,
                default => 15 + (($index * 37) % 76),
            };
            $specification['updated_at'] = $specification['created_at'];

            if ($specification['key'] === 'public-bookable-studio') {
                $specification['views'] = 600;
            }

            if ($specification['expiry'] === 'expired') {
                $specification['expires_at'] = null;
            } elseif ($specification['expiry'] === 'renewed') {
                $specification['expires_at'] = $now->addDays(30);
            } else {
                $specification['expires_at'] = null;
            }
        }
        unset($specification);

        $publicIndexes = [];
        $otherIndexes = [];
        foreach ($specifications as $index => $specification) {
            if ($specification['public']) {
                $publicIndexes[] = $index;
            } else {
                $otherIndexes[] = $index;
            }
        }
        foreach ($publicIndexes as $position => $index) {
            $specifications[$index]['gender_requirement'] = $position < 70 ? 'ANY' : ($position < 77 ? 'FEMALE' : 'MALE');
        }
        foreach ($otherIndexes as $position => $index) {
            $specifications[$index]['gender_requirement'] = $position < 30 ? 'ANY' : ($position < 46 ? 'FEMALE' : 'MALE');
        }

        $mainViews = [600, 280, 200, 160, 150, 140, 130, 120, 110, 100, 90, 80, 70, 60, 50];
        $mainIndex = 0;
        foreach ($specifications as &$specification) {
            if ($specification['owner'] === 'landlord') {
                $specification['views'] = $mainViews[$mainIndex++];
            }
        }
        unset($specification);
    }

    /** @param array<int, array<string, mixed>> $specifications
     * @param  array<string, mixed>  $actors
     * @param  array<string, mixed>  $catalog
     * @param  array{wards: array<int, array<int, int>>, regionNames: array<int, string>}  $locations
     * @param  array<int, string>  $writtenPaths
     * @return array<string, Listing>
     */
    private function createListings(
        array $specifications,
        array $actors,
        array $catalog,
        array $locations,
        array &$writtenPaths,
    ): array {
        $portfolios = [
            'landlord' => 15,
            'heavy_landlord' => 15,
            'landlord_1' => 15,
            'landlord_2' => 15,
            'landlord_3' => 15,
            'second_landlord' => 8,
            'landlord_4' => 8,
            'landlord_5' => 8,
            'landlord_6' => 8,
            'landlord_7' => 8,
            'locked_landlord' => 2,
            'landlord_8' => 2,
            'landlord_9' => 2,
            'landlord_10' => 2,
            'landlord_11' => 2,
            'landlord_12' => 2,
            'landlord_13' => 2,
            'landlord_14' => 2,
            'landlord_15' => 2,
            'landlord_16' => 2,
        ];
        $landlordKeys = [
            'landlord', 'heavy_landlord', 'second_landlord', 'locked_landlord',
            'landlord_1', 'landlord_2', 'landlord_3', 'landlord_4', 'landlord_5', 'landlord_6',
            'landlord_7', 'landlord_8', 'landlord_9', 'landlord_10', 'landlord_11', 'landlord_12',
            'landlord_13', 'landlord_14', 'landlord_15', 'landlord_16',
        ];
        $remaining = $portfolios;
        foreach ($specifications as &$specification) {
            if ($specification['owner'] !== null) {
                $remaining[$specification['owner']]--;
            }
        }
        unset($specification);

        $landlordCursor = 0;
        $ownerOrdinals = array_fill_keys(array_keys($portfolios), 0);
        $listings = [];
        $now = CarbonImmutable::now('UTC');
        $admin = $actors['admin'];

        foreach ($specifications as $index => $specification) {
            if ($specification['owner'] === null) {
                while ($remaining[$landlordKeys[$landlordCursor]] === 0) {
                    $landlordCursor++;
                }
                $ownerKey = $landlordKeys[$landlordCursor];
                $remaining[$ownerKey]--;
            } else {
                $ownerKey = $specification['owner'];
            }
            $landlord = $actors['users'][$ownerKey];
            $ownerOrdinal = $ownerOrdinals[$ownerKey]++;
            $createdAt = $specification['created_at'];
            $submittedAt = $createdAt;
            $reviewedAt = null;
            if ($specification['moderation'] !== 'PENDING') {
                $candidateReviewAt = $createdAt->addHours(3);
                $reviewedAt = $candidateReviewAt->greaterThan($now) ? $now : $candidateReviewAt;
            }
            $expiresAt = null;
            if ($specification['moderation'] === 'APPROVED') {
                $expiresAt = $specification['expiry'] === 'expired'
                    ? $reviewedAt->addDays(30)
                    : ($specification['expiry'] === 'renewed' ? $now->addDays(30) : $reviewedAt->addDays(30));
            }
            $deletionTime = $now->subDay();
            $deletedAt = $specification['deleted']
                ? ($createdAt->greaterThan($deletionTime) ? $createdAt : $deletionTime)
                : null;
            $listingUpdatedAt = $createdAt;
            if ($specification['occupancy'] === 'RENTED' || $specification['visibility'] === 'HIDDEN') {
                $stateChangeAt = $now->subDay();
                $listingUpdatedAt = $stateChangeAt->greaterThan($listingUpdatedAt) ? $stateChangeAt : $listingUpdatedAt;
            }
            $listingUpdatedAt = $deletedAt ?? $listingUpdatedAt;
            $title = $this->listingTitle($specification);
            $description = $this->listingDescription($specification);

            $listing = new Listing;
            $listing->forceFill([
                'landlord_id' => $landlord->id,
                'category_id' => $specification['category_id'],
                'current_moderation_id' => null,
                'title' => $title,
                'description' => $description,
                'monthly_rent' => $specification['monthly_rent'],
                'deposit_amount' => $specification['deposit_amount'],
                'area_m2' => $specification['area_m2'],
                'max_occupants' => $specification['max_occupants'],
                'bedroom_count' => $specification['bedroom_count'],
                'bathroom_count' => $specification['bathroom_count'],
                'gender_requirement' => $specification['gender_requirement'],
                'ward_id' => $specification['ward_id'],
                'street_address' => $specification['street_address'],
                'latitude' => null,
                'longitude' => null,
                'occupancy_status' => $specification['occupancy'],
                'visibility_status' => $specification['visibility'],
                'expires_at' => $expiresAt,
                'deleted_at' => $deletedAt,
                'view_count' => $specification['views'],
                'created_at' => $createdAt,
                'updated_at' => $listingUpdatedAt,
            ])->save();

            $moderation = $listing->moderations()->create([
                'version_no' => 1,
                'status' => $specification['moderation'],
                'reviewed_by' => $reviewedAt === null ? null : $admin->id,
                'rejection_reason' => $specification['moderation'] === 'REJECTED'
                    ? 'Thông tin minh họa cần được bổ sung hoặc rà soát.'
                    : null,
                'submitted_at' => $submittedAt,
                'reviewed_at' => $reviewedAt,
            ]);
            $listing->forceFill(['current_moderation_id' => $moderation->id])->save();

            $amenityIds = array_map(fn (string $name): int => $catalog['amenityIds'][$name], $specification['amenities']);
            $listing->amenities()->sync($amenityIds);
            $this->createListingImages($listing, $index, $writtenPaths);

            if ($ownerKey === 'landlord') {
                $specification['owner_ordinal'] = $ownerOrdinal;
            }
            $specification['landlord_key'] = $ownerKey;
            $listings[$specification['key']] = $listing->fresh(['currentModeration', 'amenities', 'category:id,name', 'ward.district.province']);
        }

        if (array_sum($remaining) !== 0) {
            throw new RuntimeException('The deterministic landlord portfolio allocation did not use every listing slot.');
        }

        return $listings;
    }

    /** @param array<string, mixed> $specification */
    private function listingTitle(array $specification): string
    {
        $specialTitles = [
            'public-bookable-studio' => 'Studio nội thất, máy lạnh — Phòng A01',
            'pending-moderation' => 'Phòng trọ chờ duyệt — Phòng A02',
            'pending-report-target' => 'Phòng trọ khu vực demo — Phòng A03',
            'expired-renewable' => 'Căn hộ mini phòng A04',
            'suspended-listing' => 'Phòng trọ thuộc hồ sơ báo cáo — Căn demo',
            'hidden-amenity-reference' => 'Studio tham chiếu tiện nghi — Phòng A05',
            'hidden-category-reference' => 'Tin tham chiếu danh mục ẩn — Căn demo',
            'locked-landlord-rented' => 'Phòng đã có người thuê — Hồ sơ chủ trọ khóa',
            'locked-landlord-visible' => 'Phòng tham chiếu tài khoản chủ trọ khóa',
        ];

        if (isset($specialTitles[$specification['key']])) {
            return $specialTitles[$specification['key']];
        }

        $category = $specification['category_name'] === 'Danh mục ẩn (Demo)'
            ? 'Phòng trọ'
            : $specification['category_name'];
        $features = [];
        foreach (['Máy lạnh', 'Nội thất', 'Wi-Fi', 'Máy giặt', 'Chỗ để xe'] as $amenity) {
            if (in_array($amenity, $specification['amenities'], true)) {
                $features[] = mb_strtolower($amenity);
            }
        }
        $featureText = $features === [] ? 'diện tích '.number_format($specification['area_m2'], 0).' m²' : implode(', ', array_slice($features, 0, 2));
        $prefix = match ($category) {
            'Phòng trọ' => 'Phòng trọ',
            'Căn hộ mini' => 'Căn hộ mini',
            'Studio' => 'Studio',
            'Chung cư' => 'Căn hộ chung cư',
            'Nhà nguyên căn' => 'Nhà nguyên căn',
            'Ở ghép' => 'Chỗ ở ghép',
            default => 'Phòng cho thuê',
        };

        return $prefix.' '.$featureText.' — Căn '.$specification['room_label'];
    }

    /** @param array<string, mixed> $specification */
    private function listingDescription(array $specification): string
    {
        $amenityText = $specification['amenities'] === []
            ? 'Chưa ghi nhận tiện ích đi kèm.'
            : 'Tiện ích niêm yết: '.implode(', ', $specification['amenities']).'.';
        $occupantText = 'Bố trí '.$specification['bedroom_count'].' phòng ngủ, '
            .$specification['bathroom_count'].' phòng vệ sinh; tối đa '
            .$specification['max_occupants'].' người.';

        return 'Diện tích '.$specification['area_m2'].' m². '.$occupantText.' '.$amenityText;
    }

    /** @param array<int, string> $writtenPaths */
    private function createListingImages(Listing $listing, int $index, array &$writtenPaths): void
    {
        $imageCount = $index < 27 ? 4 : 3;
        $now = CarbonImmutable::now('UTC');

        for ($imageIndex = 0; $imageIndex < $imageCount; $imageIndex++) {
            $path = sprintf('demo-fixtures/listings/%d/v2-%d.png', $listing->id, $imageIndex + 1);
            $writtenPaths[] = $path;
            $variant = ($index + ($imageIndex * 2)) % 6;

            if (! Storage::disk('public')->put($path, $this->placeholderPng($variant))) {
                throw new RuntimeException('A generated demo image could not be written.');
            }

            $this->listingImageRows[] = [
                'listing_id' => $listing->id,
                'image_url' => $path,
                'is_cover' => $imageIndex === 0,
                'display_order' => $imageIndex + 1,
                'created_at' => $now,
            ];
        }
    }

    /** @param array<string, Listing> $listings */
    private function createFees(array $listings): void
    {
        $types = DB::table('fee_types')->pluck('id', 'code')->all();
        $units = DB::table('fee_units')->pluck('id', 'code')->all();
        $records = array_values($listings);
        $preferences = [
            'ELECTRICITY' => ['Phòng trọ', 'Ở ghép', 'Studio', 'Căn hộ mini', 'Chung cư', 'Nhà nguyên căn'],
            'WATER' => ['Phòng trọ', 'Ở ghép', 'Căn hộ mini', 'Studio', 'Chung cư', 'Nhà nguyên căn'],
            'INTERNET' => ['Studio', 'Căn hộ mini', 'Chung cư', 'Nhà nguyên căn', 'Phòng trọ', 'Ở ghép'],
            'PARKING' => ['Chung cư', 'Nhà nguyên căn', 'Căn hộ mini', 'Studio', 'Phòng trọ', 'Ở ghép'],
            'SERVICE' => ['Căn hộ mini', 'Chung cư', 'Studio', 'Nhà nguyên căn', 'Phòng trọ', 'Ở ghép'],
        ];
        $targets = ['ELECTRICITY' => 115, 'WATER' => 100, 'INTERNET' => 75, 'PARKING' => 70, 'SERVICE' => 45];
        $unitsByType = [
            'ELECTRICITY' => 'PER_KWH',
            'WATER' => 'PER_M3',
            'INTERNET' => 'FIXED_MONTHLY',
            'PARKING' => 'PER_VEHICLE',
            'SERVICE' => 'FIXED_MONTHLY',
        ];
        $now = CarbonImmutable::now('UTC');
        $rows = [];

        foreach ($targets as $code => $target) {
            $priority = array_flip($preferences[$code]);
            $ordered = $records;
            usort($ordered, function (Listing $left, Listing $right) use ($priority): int {
                $leftRank = $priority[$left->category->name] ?? 99;
                $rightRank = $priority[$right->category->name] ?? 99;

                return $leftRank <=> $rightRank ?: $left->id <=> $right->id;
            });

            foreach (array_slice($ordered, 0, $target) as $index => $listing) {
                $amount = match ($code) {
                    'ELECTRICITY' => 3200 + (($index * 7) % 10) * 100,
                    'WATER' => 18000 + (($index * 13) % 11) * 1500,
                    'INTERNET' => 90000 + (($index * 19) % 10) * 10000,
                    'PARKING' => 80000 + (($index * 23) % 8) * 25000,
                    default => 50000 + (($index * 17) % 12) * 15000,
                };
                $rows[] = [
                    'listing_id' => $listing->id,
                    'fee_type_id' => $types[$code],
                    'fee_unit_id' => $units[$unitsByType[$code]],
                    'amount' => $amount,
                    'note' => 'Mức phí minh họa trong dữ liệu demo.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('listing_fees')->insert($rows);
    }

    /** @param array<string, mixed> $actors
     * @param  array<string, Listing>  $listings
     * @param  Collection<int, Listing>  $publicListings
     */
    private function createFavorites(array $actors, array $listings, $publicListings): void
    {
        $otherActiveRenters = array_values(array_filter(
            $actors['active_renter_only'],
            fn (User $user): bool => (int) $user->id !== (int) $actors['renter']->id,
        ));
        $favoriteUsers = array_slice(array_merge([$actors['renter']], $otherActiveRenters, array_values(array_filter(
            $actors['landlords'], fn (User $landlord): bool => $landlord->account_status === 'ACTIVE',
        ))), 0, 70);
        $publicListings->loadMissing('currentModeration');
        $public = $publicListings->values()->all();
        $unavailableKeys = [
            'main-rented-expired',
            ...array_map(fn (int $index): string => sprintf('market-rented-expired-%02d', $index), range(1, 13)),
            'expired-renewable',
            ...array_map(fn (int $index): string => sprintf('market-expired-available-%02d', $index), range(1, 3)),
            'main-rented-current',
            'market-rented-current',
            'main-hidden-available',
            'suspended-listing',
            'locked-landlord-visible',
            'deleted-approved-rented',
            'deleted-approved-hidden',
        ];
        $unavailable = array_map(fn (string $key): Listing => $listings[$key], $unavailableKeys);
        $retainedFavoriteTimes = [];
        $lockedLandlordUpdatedAt = CarbonImmutable::parse($actors['locked_landlord']->fresh()->updated_at, 'UTC');
        foreach ($unavailable as $listing) {
            $eventAt = match (true) {
                $listing->expires_at !== null && $listing->expires_at->lessThanOrEqualTo(now()) => CarbonImmutable::parse($listing->expires_at, 'UTC'),
                $listing->deleted_at !== null => CarbonImmutable::parse($listing->deleted_at, 'UTC'),
                (int) $listing->landlord_id === (int) $actors['locked_landlord']->id => $lockedLandlordUpdatedAt,
                default => CarbonImmutable::parse($listing->updated_at, 'UTC'),
            };
            $approvedAt = CarbonImmutable::parse($listing->currentModeration->reviewed_at, 'UTC')->addHour();
            $favoriteAt = $eventAt->subDay();
            $retainedFavoriteTimes[$listing->id] = $favoriteAt->greaterThan($approvedAt) ? $favoriteAt : $approvedAt;
        }
        $rows = [];
        $now = CarbonImmutable::now('UTC');

        foreach ($favoriteUsers as $userIndex => $user) {
            $favoriteCount = $userIndex < 10 ? 10 : ($userIndex < 40 ? 7 : 4);
            for ($favoriteIndex = 0; $favoriteIndex < $favoriteCount; $favoriteIndex++) {
                $isUnavailableFavorite = $favoriteIndex === 0 && $userIndex <= 24;
                $listing = $isUnavailableFavorite
                    ? $unavailable[$userIndex]
                    : $public[($userIndex * 11 + $favoriteIndex * 7) % count($public)];
                $createdAt = $isUnavailableFavorite
                    ? $retainedFavoriteTimes[$listing->id]
                    : max($listing->currentModeration->reviewed_at->copy()->addHour(), $now->subDays(2));
                if ($createdAt->greaterThan($now)) {
                    $createdAt = $now;
                }
                $rows[] = ['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => $createdAt];
            }
        }

        DB::table('favorites')->insert($rows);
    }

    /** @param array<string, mixed> $actors
     * @param  array<string, Listing>  $listings
     * @param  Collection<int, Listing>  $publicListings
     */
    private function createViewingSlotsAndAppointments(array $actors, array $listings, $publicListings): void
    {
        $localNow = CarbonImmutable::now(ViewingSlot::TIMEZONE);
        $utcNow = CarbonImmutable::now('UTC');
        $public = $publicListings->values()->all();
        $appointmentListings = array_values(array_filter($public, fn (Listing $listing): bool => CarbonImmutable::parse($listing->created_at, 'UTC')->lessThanOrEqualTo($utcNow->subDays(12))));
        $mainPublic = array_values(array_filter($appointmentListings, fn (Listing $listing): bool => (int) $listing->landlord_id === (int) $actors['landlord']->id));
        $otherPublic = array_values(array_filter($appointmentListings, fn (Listing $listing): bool => (int) $listing->landlord_id !== (int) $actors['landlord']->id));

        if (count($mainPublic) < 4 || $otherPublic === []) {
            throw new RuntimeException('The demo booking fixtures require established public listings for each active landlord group.');
        }
        $used = [];
        $slotCount = 0;
        $closedCount = 0;
        $pastIndex = 0;
        $futureIndex = 0;
        $acceptedTodayIndex = 0;
        $appointmentCounts = [
            'COMPLETED' => 55,
            'AUTO_CANCELLED' => 20,
            'PENDING' => 28,
            'ACCEPTED' => 22,
            'REJECTED' => 25,
            'CANCELLED' => 30,
        ];
        $renterPool = $actors['active_renter_only'];
        $appointmentIndex = 0;

        foreach ($appointmentCounts as $status => $count) {
            for ($statusIndex = 0; $statusIndex < $count; $statusIndex++) {
                $isPast = in_array($status, ['COMPLETED', 'AUTO_CANCELLED'], true);
                $isTodayAccepted = $status === 'ACCEPTED' && $acceptedTodayIndex < 6;

                if ($status === 'PENDING' && $statusIndex < 4) {
                    $listing = $mainPublic[$statusIndex];
                } elseif ($status === 'PENDING') {
                    $listing = $otherPublic[($futureIndex * 13) % count($otherPublic)];
                } else {
                    $listing = $appointmentListings[($appointmentIndex * 17) % count($appointmentListings)];
                }

                $slotStatus = $isPast && $closedCount < 70 ? 'CLOSED' : 'OPEN';
                if ($slotStatus === 'CLOSED') {
                    $closedCount++;
                }

                if ($isPast) {
                    $start = $this->pastSlotStart($localNow, $pastIndex, $listing, $used);
                    $pastIndex++;
                } else {
                    $start = $this->futureSlotStart($localNow, $futureIndex, $isTodayAccepted ? $acceptedTodayIndex : null, $listing->id, $used);
                    if ($isTodayAccepted) {
                        $acceptedTodayIndex++;
                    }
                    $futureIndex++;
                }

                $slot = $this->persistViewingSlot($listing, $start, $slotStatus, $used);
                $slotCount++;
                $renter = $renterPool[($appointmentIndex * 13 + $statusIndex) % count($renterPool)];
                $this->persistAppointment($slot, $renter, $status, $start, $utcNow, $appointmentIndex);
                $appointmentIndex++;
            }
        }

        for ($index = 0; $index < 135; $index++) {
            $listing = $appointmentListings[($pastIndex * 19 + $index) % count($appointmentListings)];
            $start = $this->pastSlotStart($localNow, $pastIndex, $listing, $used);
            $slot = $this->persistViewingSlot($listing, $start, 'OPEN', $used);
            $pastIndex++;
            $slotCount++;
        }

        $otherPublicListings = array_values(array_filter(
            $public,
            fn (Listing $listing): bool => (int) $listing->id !== (int) $listings['public-bookable-studio']->id,
        ));
        for ($index = 0; $index < 5; $index++) {
            $listing = $index === 0
                ? $listings['public-bookable-studio']
                : $otherPublicListings[($futureIndex * 11 + $index) % count($otherPublicListings)];
            $start = $this->futureSlotStart($localNow, $futureIndex, null, $listing->id, $used, 2 + $index);
            $slot = $this->persistViewingSlot($listing, $start, 'OPEN', $used);
            $futureIndex++;
            $slotCount++;
        }

        if ($slotCount !== 320 || $closedCount !== 70 || $acceptedTodayIndex !== 6) {
            throw new RuntimeException('The deterministic viewing schedule did not match its exact target distribution.');
        }
    }

    /** @param array<int, array<string, array<int, array{start: string, end: string, status: string}>>> $used */
    private function pastSlotStart(CarbonImmutable $localNow, int $sequence, Listing $listing, array &$used): CarbonImmutable
    {
        $times = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
        $earliestAfterApproval = CarbonImmutable::parse($listing->created_at, 'UTC')
            ->setTimezone(ViewingSlot::TIMEZONE)
            ->addDays(4);

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $daysAgo = 1 + (($sequence * 17 + $attempt * 29) % 180);
            $time = $times[($sequence + $attempt * 3) % count($times)];
            $start = CarbonImmutable::parse($localNow->subDays($daysAgo)->toDateString().' '.$time, ViewingSlot::TIMEZONE);
            if ($start->lessThanOrEqualTo($earliestAfterApproval)) {
                continue;
            }
            if ($this->slotPlacementIsValid((int) $listing->id, $start, 'OPEN', $used)) {
                return $start;
            }
        }

        throw new RuntimeException('Could not find a deterministic non-overlapping past viewing slot.');
    }

    /** @param array<int, array<string, array<int, array{start: string, end: string, status: string}>>> $used */
    private function futureSlotStart(
        CarbonImmutable $localNow,
        int $sequence,
        ?int $todaySequence,
        int $listingId,
        array &$used,
        ?int $minimumDays = null,
    ): CarbonImmutable {
        if ($todaySequence !== null) {
            return $localNow->startOfDay()->addMinutes(15 + $todaySequence * 30);
        }

        $times = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $daysAhead = ($minimumDays ?? 4) + (($sequence * 7 + $attempt * 11) % 21);
            $time = $times[($sequence + $attempt * 3) % count($times)];
            $start = CarbonImmutable::parse($localNow->addDays($daysAhead)->toDateString().' '.$time, ViewingSlot::TIMEZONE);
            if ($this->slotPlacementIsValid($listingId, $start, 'OPEN', $used)) {
                return $start;
            }
        }

        throw new RuntimeException('Could not find a deterministic non-overlapping future viewing slot.');
    }

    /** @param array<int, array<string, array<int, array{start: string, end: string, status: string}>>> $used */
    private function slotPlacementIsValid(int $listingId, CarbonImmutable $start, string $status, array $used): bool
    {
        $date = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $start->addMinutes(30)->format('H:i:s');

        foreach ($used[$listingId][$date] ?? [] as $existing) {
            if ($existing['start'] === $startTime && $existing['end'] === $endTime) {
                return false;
            }
            if ($status === 'OPEN' && $existing['status'] === 'OPEN'
                && $startTime < $existing['end'] && $endTime > $existing['start']) {
                return false;
            }
        }

        return true;
    }

    private function persistViewingSlot(Listing $listing, CarbonImmutable $start, string $status, array &$used): ViewingSlot
    {
        $listingCreatedAt = CarbonImmutable::parse($listing->created_at, 'UTC');
        $earliestSlotCreation = $listingCreatedAt->addHour();
        $candidateCreatedAt = $start->subDays(31)->utc();
        $createdAt = $candidateCreatedAt->greaterThan($earliestSlotCreation)
            ? $candidateCreatedAt
            : $earliestSlotCreation;
        $date = $start->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $start->addMinutes(30)->format('H:i:s');

        if (! $this->slotPlacementIsValid((int) $listing->id, $start, $status, $used)) {
            throw new RuntimeException('A deterministic viewing slot overlapped or duplicated an existing slot.');
        }

        $slot = new ViewingSlot;
        $slot->forceFill([
            'listing_id' => $listing->id,
            'viewing_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $used[$listing->id][$date][] = ['start' => $startTime, 'end' => $endTime, 'status' => $status];

        return $slot;
    }

    private function persistAppointment(
        ViewingSlot $slot,
        User $renter,
        string $status,
        CarbonImmutable $start,
        CarbonImmutable $utcNow,
        int $index,
    ): void {
        $end = $start->addMinutes(30);
        $createdAt = match ($status) {
            'COMPLETED', 'AUTO_CANCELLED' => $start->subDays(3)->utc(),
            'PENDING' => $utcNow->subHours(2 + ($index % 8)),
            'ACCEPTED' => $start->toDateString() === CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString()
                ? $start->subDays(3)->utc()
                : $utcNow->subDays(5),
            'REJECTED' => $utcNow->subDays(10),
            'CANCELLED' => $utcNow->subDays(8),
            default => $utcNow->subDay(),
        };
        $respondedAt = match ($status) {
            'ACCEPTED' => $start->toDateString() === CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString()
                ? $start->subDays(1)->utc()
                : $utcNow->subDays(2),
            'REJECTED' => $utcNow->subDays(9),
            'COMPLETED' => $start->subDays(1)->utc(),
            default => null,
        };
        $cancelledAt = match ($status) {
            'CANCELLED' => $utcNow->subDays(2 + ($index % 2)),
            'AUTO_CANCELLED' => $start->addMinutes(30)->utc(),
            default => null,
        };
        $completedAt = $status === 'COMPLETED' ? $end->addMinutes(15)->utc() : null;
        $cancelledBy = $status === 'CANCELLED' ? $renter->id : null;
        $reason = match ($status) {
            'CANCELLED' => 'DEMO_RENTER_CANCELLED',
            'AUTO_CANCELLED' => 'VIEWING_TIME_PASSED',
            default => null,
        };

        $appointment = new Appointment;
        $appointment->forceFill([
            'slot_id' => $slot->id,
            'renter_id' => $renter->id,
            'status' => $status,
            'renter_note' => 'Lịch xem phòng minh họa cho dữ liệu demo.',
            'landlord_response' => in_array($status, ['ACCEPTED', 'REJECTED', 'COMPLETED'], true)
                ? 'Phản hồi lịch hẹn minh họa.'
                : null,
            'cancelled_by' => $cancelledBy,
            'cancellation_reason' => $reason,
            'responded_at' => $respondedAt,
            'completed_at' => $completedAt,
            'cancelled_at' => $cancelledAt,
            'created_at' => $createdAt,
            'updated_at' => $cancelledAt ?? $completedAt ?? $respondedAt ?? $createdAt,
        ])->save();
    }

    /** @param array<string, mixed> $actors
     * @param  array<string, Listing>  $listings
     */
    private function createReports(array $actors, array $listings): void
    {
        $reasonIds = DB::table('report_reasons')->pluck('id', 'code')->all();
        $reasonStatusCounts = [
            'PENDING' => ['WRONG_ADDRESS' => 6, 'WRONG_PRICE' => 5, 'FRAUD' => 3, 'INAPPROPRIATE_CONTENT' => 2, 'OTHER' => 2],
            'DISMISSED' => ['WRONG_ADDRESS' => 4, 'WRONG_PRICE' => 4, 'FRAUD' => 2, 'INAPPROPRIATE_CONTENT' => 2, 'OTHER' => 2],
            'RESOLVED' => ['WRONG_ADDRESS' => 8, 'WRONG_PRICE' => 6, 'FRAUD' => 4, 'INAPPROPRIATE_CONTENT' => 4, 'OTHER' => 6],
        ];
        $reasonLists = [];
        foreach ($reasonStatusCounts as $status => $counts) {
            foreach ($counts as $reason => $count) {
                for ($index = 0; $index < $count; $index++) {
                    $reasonLists[$status][] = $reason;
                }
            }
        }

        $suspendedListings = array_values(array_filter($listings, fn (Listing $listing): bool => $listing->visibility_status === 'SUSPENDED'));
        $reportListingPool = array_values(array_filter($listings, fn (Listing $listing): bool => $listing->deleted_at === null));
        $reporterPool = $actors['active_renter_only'];
        $now = CarbonImmutable::now('UTC');
        $resolvedIndex = 0;
        $index = 0;

        foreach ([['PENDING', 18], ['DISMISSED', 14], ['RESOLVED', 28]] as [$status, $count]) {
            for ($statusIndex = 0; $statusIndex < $count; $statusIndex++) {
                $listing = match (true) {
                    $status === 'PENDING' && $statusIndex === 0 => $listings['pending-report-target'],
                    $status === 'RESOLVED' && $resolvedIndex < 5 => $suspendedListings[$resolvedIndex],
                    $status === 'RESOLVED' && $resolvedIndex === 5 => $listings['locked-landlord-rented'],
                    default => $reportListingPool[($index * 17 + 13) % count($reportListingPool)],
                };
                $reasonCode = $reasonLists[$status][$statusIndex];
                $reporter = $reporterPool[($index * 11) % count($reporterPool)];
                $listingCreatedAt = CarbonImmutable::parse($listing->created_at, 'UTC');
                $desiredReportAt = $now->subDays(12 + ($index % 5));
                $createdAt = $listingCreatedAt->greaterThan($desiredReportAt)
                    ? $listingCreatedAt
                    : $desiredReportAt;
                $candidateHandledAt = $createdAt->addDay();
                $handledAt = $status === 'PENDING'
                    ? null
                    : ($candidateHandledAt->greaterThan($now) ? $now : $candidateHandledAt);
                $actionType = null;
                if ($status === 'RESOLVED') {
                    $actionType = match (true) {
                        $resolvedIndex < 5 => 'SUSPEND_LISTING',
                        $resolvedIndex === 5 => 'LOCK_ACCOUNT',
                        default => 'WARNING',
                    };
                }

                $report = new Report;
                $report->forceFill([
                    'reporter_id' => $reporter->id,
                    'listing_id' => $listing->id,
                    'reason_id' => $reasonIds[$reasonCode],
                    'description' => 'Nội dung báo cáo tổng hợp giả lập để minh họa hàng đợi xử lý.',
                    'status' => $status,
                    'handled_by' => $handledAt === null ? null : $actors['admin']->id,
                    'resolution_reason' => $status === 'PENDING'
                        ? null
                        : ($status === 'DISMISSED' ? 'Thông tin minh họa chưa đủ căn cứ xử lý.' : 'Đã rà soát nội dung trong hồ sơ demo.'),
                    'handled_at' => $handledAt,
                    'created_at' => $createdAt,
                    'updated_at' => $handledAt ?? $createdAt,
                ])->save();

                if ($actionType !== null) {
                    $this->createEnforcementAction($report, $actors, $listing, $actionType, $handledAt, $resolvedIndex);
                    $resolvedIndex++;
                }

                if ($handledAt !== null) {
                    AuditLog::query()->create([
                        'actor_user_id' => $actors['admin']->id,
                        'action' => match ($actionType) {
                            'WARNING' => 'report.resolved.warning',
                            'SUSPEND_LISTING' => 'report.resolved.suspend_listing',
                            'LOCK_ACCOUNT' => 'report.resolved.lock_account',
                            default => 'report.dismissed',
                        },
                        'entity_type' => Report::class,
                        'entity_id' => $report->id,
                        'created_at' => $handledAt,
                    ]);
                }

                $index++;
            }
        }
    }

    /** @param array<string, mixed> $actors */
    private function createEnforcementAction(
        Report $report,
        array $actors,
        Listing $listing,
        string $actionType,
        CarbonImmutable $handledAt,
        int $resolvedIndex,
    ): void {
        $targetUserId = match ($actionType) {
            'WARNING' => $listing->landlord_id,
            'LOCK_ACCOUNT' => $actors['locked_landlord']->id,
            default => null,
        };
        $targetListingId = $actionType === 'SUSPEND_LISTING' ? $listing->id : null;

        EnforcementAction::query()->create([
            'report_id' => $report->id,
            'admin_id' => $actors['admin']->id,
            'action_type' => $actionType,
            'target_user_id' => $targetUserId,
            'target_listing_id' => $targetListingId,
            'reason' => 'Quyết định xử lý minh họa số '.($resolvedIndex + 1).'.',
            'created_at' => $handledAt,
        ]);

        if ($actionType === 'SUSPEND_LISTING') {
            $listing->forceFill(['updated_at' => $handledAt])->save();
        }
        if ($actionType === 'LOCK_ACCOUNT') {
            $actors['locked_landlord']->forceFill(['updated_at' => $handledAt])->save();
        }
    }

    /** @param array<string, mixed> $actors */
    private function createLockedAccountHistory(array $actors): void
    {
        $now = CarbonImmutable::now('UTC')->subDays(10);
        $lockedRenters = array_values(array_filter($actors['renter_only'], fn (User $user): bool => $user->account_status === 'LOCKED'));

        foreach ($lockedRenters as $index => $user) {
            $lockedAt = $now->addMinutes($index);
            AuditLog::query()->create([
                'actor_user_id' => $actors['admin']->id,
                'action' => 'user.locked',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'created_at' => $lockedAt,
            ]);
            $user->forceFill(['updated_at' => $lockedAt])->save();
        }
    }

    private function assertFinalCounts(): void
    {
        $expected = [
            'users' => 120,
            'user_profiles' => 120,
            'user_roles' => 140,
            'listings' => 135,
            'provinces' => 4,
            'districts' => 12,
            'wards' => 48,
            'amenities' => 6,
            'viewing_slots' => 320,
            'appointments' => 180,
            'reports' => 60,
            'enforcement_actions' => 28,
            'favorites' => 430,
            'listing_images' => 432,
            'listing_fees' => 405,
        ];

        foreach ($expected as $table => $count) {
            if (DB::table($table)->count() !== $count) {
                throw new RuntimeException("Realistic Demo V2 generated an unexpected {$table} count.");
            }
        }
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function placeholderPixel(int $x, int $y, int $variant): array
    {
        $scenes = [
            ['wall' => [244, 231, 211], 'floor' => [190, 155, 119], 'window' => [128, 190, 202], 'furniture' => [72, 114, 127], 'accent' => [224, 157, 96], 'dark' => [69, 67, 64]],
            ['wall' => [228, 232, 219], 'floor' => [177, 157, 125], 'window' => [133, 182, 190], 'furniture' => [107, 126, 100], 'accent' => [213, 153, 121], 'dark' => [60, 71, 65]],
            ['wall' => [235, 226, 220], 'floor' => [178, 146, 129], 'window' => [129, 177, 191], 'furniture' => [131, 101, 91], 'accent' => [220, 183, 113], 'dark' => [75, 62, 59]],
            ['wall' => [221, 231, 236], 'floor' => [174, 160, 137], 'window' => [111, 166, 193], 'furniture' => [89, 119, 143], 'accent' => [218, 169, 116], 'dark' => [56, 72, 84]],
            ['wall' => [239, 233, 211], 'floor' => [179, 155, 117], 'window' => [126, 178, 181], 'furniture' => [143, 116, 78], 'accent' => [205, 133, 91], 'dark' => [73, 70, 58]],
            ['wall' => [231, 222, 235], 'floor' => [174, 151, 177], 'window' => [121, 174, 193], 'furniture' => [112, 96, 142], 'accent' => [222, 164, 123], 'dark' => [67, 59, 79]],
        ];
        $scene = $scenes[$variant];
        $color = $y >= 177 ? $scene['floor'] : $scene['wall'];

        if ($x >= 33 && $x <= 116 && $y >= 36 && $y <= 112) {
            $color = $scene['window'];
            if ($x <= 39 || $x >= 110 || $y <= 42 || $y >= 106 || ($x >= 73 && $x <= 77)) {
                $color = $scene['dark'];
            }
        }

        if ($variant % 3 === 0) {
            if ($x >= 120 && $x <= 287 && $y >= 134 && $y <= 177) {
                $color = $scene['furniture'];
            }
            if ($x >= 133 && $x <= 276 && $y >= 119 && $y <= 150) {
                $color = [238, 235, 221];
            }
            if ($x >= 145 && $x <= 184 && $y >= 121 && $y <= 140) {
                $color = $scene['accent'];
            }
        } elseif ($variant % 3 === 1) {
            if ($x >= 116 && $x <= 280 && $y >= 139 && $y <= 177) {
                $color = $scene['furniture'];
            }
            if ($x >= 132 && $x <= 184 && $y >= 127 && $y <= 151) {
                $color = $scene['accent'];
            }
            if ($x >= 194 && $x <= 255 && $y >= 127 && $y <= 151) {
                $color = $scene['furniture'];
            }
        } else {
            if ($x >= 132 && $x <= 245 && $y >= 132 && $y <= 176) {
                $color = $scene['furniture'];
            }
            if ($x >= 144 && $x <= 232 && $y >= 119 && $y <= 143) {
                $color = [238, 235, 221];
            }
            if ($x >= 252 && $x <= 284 && $y >= 145 && $y <= 176) {
                $color = $scene['accent'];
            }
        }

        if ($x >= 45 && $x <= 62 && $y >= 142 && $y <= 176) {
            $color = $scene['accent'];
        }
        if ($x >= 47 && $x <= 77 && $y >= 128 && $y <= 147) {
            $color = $scene['furniture'];
        }
        if ($y >= 218) {
            $color = $scene['dark'];
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
