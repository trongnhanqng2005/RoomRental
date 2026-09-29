<?php

namespace Tests\Feature\Database;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\ViewingSlot;
use App\Queries\AdminDashboardQuery;
use App\Queries\LandlordDashboardQuery;
use App\Queries\ListingSearchQuery;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_demo_seeder_creates_the_deterministic_v2_dataset_and_preserves_invariants(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $frozenNow = Carbon::parse('2026-09-28 12:00:00', 'UTC');
        Carbon::setTestNow($frozenNow);
        CarbonImmutable::setTestNow($frozenNow->toImmutable());
        $this->seed(DatabaseSeeder::class);
        $originalTimeZone = DB::selectOne('SELECT @@session.time_zone AS time_zone')->time_zone;

        app(DemoSeeder::class)->run();
        $this->assertSame($originalTimeZone, DB::selectOne('SELECT @@session.time_zone AS time_zone')->time_zone);

        foreach ([
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
        ] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }

        $renter = User::query()->where('email', 'demo-renter@roomrental.test')->firstOrFail();
        $landlord = User::query()->where('email', 'demo-landlord@roomrental.test')->firstOrFail();
        $admin = User::query()->where('email', 'demo-admin@roomrental.test')->firstOrFail();
        $lockedLandlord = User::query()->where('email', 'demo-landlord-locked@roomrental.test')->firstOrFail();
        $this->assertTrue(Hash::check(DemoSeeder::DEMO_PASSWORD, $renter->password_hash));
        $this->assertTrue($renter->hasRole('RENTER'));
        $this->assertTrue($landlord->hasRole('RENTER'));
        $this->assertTrue($landlord->hasRole('LANDLORD'));
        $this->assertTrue($admin->hasRole('ADMIN'));
        $this->assertSame('LOCKED', $lockedLandlord->account_status);
        $this->assertSame(4, User::query()->where('account_status', 'LOCKED')->count());
        $this->assertSame(3, User::query()->get()->filter(fn (User $user): bool => $user->account_status === 'LOCKED' && $user->hasRole('RENTER') && ! $user->hasRole('LANDLORD'))->count());
        $this->assertSame(97, User::query()->get()->filter(fn (User $user): bool => $user->hasRole('RENTER') && ! $user->hasRole('LANDLORD') && ! $user->hasRole('ADMIN'))->count());
        $this->assertSame(20, User::query()->get()->filter(fn (User $user): bool => $user->hasRole('RENTER') && $user->hasRole('LANDLORD'))->count());
        $this->assertSame(3, User::query()->get()->filter(fn (User $user): bool => $user->hasRole('ADMIN') && ! $user->hasRole('RENTER') && ! $user->hasRole('LANDLORD'))->count());
        $this->assertSame(0, DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('roles.code', 'SUPER_ADMIN')->count());
        $this->assertSame(3, DB::table('users')->join('user_roles', 'user_roles.user_id', '=', 'users.id')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('roles.code', 'ADMIN')->where('users.account_status', 'ACTIVE')->count());
        $this->assertDatabaseHas('room_categories', ['name' => 'Danh mục ẩn (Demo)', 'is_active' => false]);
        $this->assertDatabaseHas('amenities', ['name' => 'Ban công', 'is_active' => false]);

        $stateRows = DB::table('listings')
            ->join('listing_moderations as current_moderation', function ($join): void {
                $join->on('current_moderation.id', '=', 'listings.current_moderation_id')
                    ->on('current_moderation.listing_id', '=', 'listings.id');
            })
            ->select('current_moderation.status as moderation', 'listings.occupancy_status as occupancy', 'listings.visibility_status as visibility', 'listings.deleted_at')
            ->get();
        $this->assertSame(['APPROVED' => 106, 'PENDING' => 20, 'REJECTED' => 9], $stateRows->countBy('moderation')->sortKeys()->all());
        $this->assertSame(['AVAILABLE' => 113, 'RENTED' => 22], $stateRows->countBy('occupancy')->sortKeys()->all());
        $this->assertSame(['HIDDEN' => 17, 'SUSPENDED' => 8, 'VISIBLE' => 110], $stateRows->countBy('visibility')->sortKeys()->all());
        $this->assertSame(3, $stateRows->whereNotNull('deleted_at')->count());
        $deletedStates = $stateRows->whereNotNull('deleted_at');
        $this->assertSame(['APPROVED' => 2, 'PENDING' => 1], $deletedStates->countBy('moderation')->sortKeys()->all());
        $this->assertSame(['AVAILABLE' => 2, 'RENTED' => 1], $deletedStates->countBy('occupancy')->sortKeys()->all());
        $this->assertSame(['HIDDEN' => 1, 'VISIBLE' => 2], $deletedStates->countBy('visibility')->sortKeys()->all());
        $activeStates = $stateRows->whereNull('deleted_at');
        $this->assertSame(['APPROVED' => 104, 'PENDING' => 19, 'REJECTED' => 9], $activeStates->countBy('moderation')->sortKeys()->all());
        $this->assertSame(['AVAILABLE' => 111, 'RENTED' => 21], $activeStates->countBy('occupancy')->sortKeys()->all());
        $this->assertSame(['HIDDEN' => 16, 'SUSPENDED' => 8, 'VISIBLE' => 108], $activeStates->countBy('visibility')->sortKeys()->all());
        $this->assertSame(18, Listing::query()->whereHas('currentModeration', fn ($query) => $query->where('status', 'APPROVED'))->where('expires_at', '<=', now())->count());

        $portfolioCounts = DB::table('listings')->select('landlord_id')->selectRaw('COUNT(*) AS listing_count')->groupBy('landlord_id')->pluck('listing_count')->map(fn ($count): int => (int) $count)->sort()->values()->all();
        $this->assertSame(array_merge(array_fill(0, 10, 2), array_fill(0, 5, 8), array_fill(0, 5, 15)), $portfolioCounts);
        $this->assertSame(15, Listing::query()->where('landlord_id', $landlord->id)->count());
        $this->assertSame(2, Listing::query()->where('landlord_id', $lockedLandlord->id)->count());
        $this->assertSame(0, app(ListingSearchQuery::class)->build()->where('landlord_id', $lockedLandlord->id)->count());

        $categoryCounts = DB::table('listings')->join('room_categories', 'room_categories.id', '=', 'listings.category_id')->select('room_categories.name')->selectRaw('COUNT(*) AS listing_count')->groupBy('room_categories.name')->pluck('listing_count', 'name')->map(fn ($count): int => (int) $count)->all();
        ksort($categoryCounts);
        $expectedCategoryCounts = [
            'Căn hộ mini' => 27,
            'Chung cư' => 15,
            'Danh mục ẩn (Demo)' => 1,
            'Nhà nguyên căn' => 10,
            'Phòng trọ' => 53,
            'Studio' => 21,
            'Ở ghép' => 8,
        ];
        ksort($expectedCategoryCounts);
        $this->assertSame($expectedCategoryCounts, $categoryCounts);
        $regionCounts = DB::table('listings')
            ->join('wards', 'wards.id', '=', 'listings.ward_id')
            ->join('districts', 'districts.id', '=', 'wards.district_id')
            ->join('provinces', 'provinces.id', '=', 'districts.province_id')
            ->select('provinces.name')->selectRaw('COUNT(*) AS listing_count')->groupBy('provinces.name')->pluck('listing_count', 'name')->map(fn ($count): int => (int) $count)->all();
        ksort($regionCounts);
        $expectedRegionCounts = ['TP.HCM' => 54, 'Hà Nội' => 40, 'Đà Nẵng' => 25, 'Cần Thơ' => 16];
        ksort($expectedRegionCounts);
        $this->assertSame($expectedRegionCounts, $regionCounts);
        $this->assertSame(0, DB::table('districts')->leftJoin('provinces', 'provinces.id', '=', 'districts.province_id')->whereNull('provinces.id')->count());
        $this->assertSame(0, DB::table('wards')->leftJoin('districts', 'districts.id', '=', 'wards.district_id')->whereNull('districts.id')->count());

        $publicListings = app(ListingSearchQuery::class)->build()->orderBy('listings.id')->get();
        $this->assertCount(80, $publicListings);
        $this->assertSame(8, app(ListingSearchQuery::class)->build()->paginate(10)->lastPage());
        $this->assertSame(80, app(ListingSearchQuery::class)->build()->count());
        $this->assertGreaterThan(1, app(ListingSearchQuery::class)->build(['q' => 'Studio'])->count());
        $bookableSlotCount = $publicListings->sum(fn (Listing $listing): int => app(AppointmentService::class)->bookableSlotsForListing($listing)->count());
        $this->assertGreaterThanOrEqual(25, $bookableSlotCount);

        $bookingFixture = Listing::query()->where('title', 'Studio nội thất, máy lạnh — Phòng A01')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, app(AppointmentService::class)->bookableSlotsForListing($bookingFixture)->count());
        $bookingAmenityIds = $bookingFixture->amenities()->whereIn('name', ['Máy lạnh', 'Nội thất'])->pluck('amenities.id')->map(fn ($id): int => (int) $id)->all();
        $this->assertCount(2, $bookingAmenityIds);
        $this->assertSame(1, app(ListingSearchQuery::class)->build([
            'q' => $bookingFixture->title,
            'ward_id' => $bookingFixture->ward_id,
            'amenity_ids' => $bookingAmenityIds,
        ])->count());
        $this->assertSame(0, app(ListingSearchQuery::class)->build([
            'ward_id' => $bookingFixture->ward_id,
            'max_price' => 1,
        ])->count());
        $this->assertGreaterThan(0, app(ListingSearchQuery::class)->build(['gender_requirement' => 'FEMALE'])->count());
        $this->assertGreaterThan(0, app(ListingSearchQuery::class)->build(['gender_requirement' => 'MALE'])->count());
        $anyResults = app(ListingSearchQuery::class)->build(['gender_requirement' => 'ANY'])->pluck('id')->all();
        $femaleResults = app(ListingSearchQuery::class)->build(['gender_requirement' => 'FEMALE'])->pluck('id')->all();
        $this->assertNotEmpty(array_diff($femaleResults, $anyResults));
        $this->assertSame(100, Listing::query()->where('gender_requirement', 'ANY')->count());
        $this->assertSame(23, Listing::query()->where('gender_requirement', 'FEMALE')->count());
        $this->assertSame(12, Listing::query()->where('gender_requirement', 'MALE')->count());

        $sorts = ['newest', 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'most_viewed'];
        $firstIds = [];
        foreach ($sorts as $sort) {
            $firstIds[] = app(ListingSearchQuery::class)->build(['sort' => $sort])->value('id');
        }
        $this->assertGreaterThanOrEqual(4, count(array_unique($firstIds)));

        foreach (Listing::query()->with(['currentModeration', 'images', 'ward.district.province'])->get() as $listing) {
            $this->assertNotNull($listing->currentModeration);
            $this->assertSame($listing->id, $listing->currentModeration->listing_id);
            $this->assertNotNull($listing->ward?->district?->province);
            $this->assertContains($listing->images->count(), [3, 4]);
            $this->assertSame(1, $listing->images->where('is_cover', true)->count());
            $this->assertDoesNotMatchRegularExpression('/^Listing\s+\d+$/i', $listing->title);
        }

        $hiddenAmenity = DB::table('amenities')->where('name', 'Ban công')->first();
        $this->assertFalse((bool) $hiddenAmenity->is_active);
        $this->assertSame(1, DB::table('listing_amenities')->where('amenity_id', $hiddenAmenity->id)->count());
        $this->assertSame(1, DB::table('listings')->join('listing_amenities', 'listing_amenities.listing_id', '=', 'listings.id')->where('listing_amenities.amenity_id', $hiddenAmenity->id)->where('listings.title', 'Studio tham chiếu tiện nghi — Phòng A05')->count());
        $this->assertSame(1, Listing::query()->where('title', 'Tin tham chiếu danh mục ẩn — Căn demo')->whereHas('category', fn ($query) => $query->where('is_active', false))->count());
        $this->assertGreaterThanOrEqual(6, DB::table('listings')->select('landlord_id', 'ward_id', 'street_address')->groupBy('landlord_id', 'ward_id', 'street_address')->havingRaw('COUNT(*) > 1')->count());

        $imageGroups = DB::table('listing_images')->select('listing_id')->selectRaw('COUNT(*) AS image_count, SUM(is_cover) AS cover_count')->groupBy('listing_id')->get();
        $this->assertCount(135, $imageGroups);
        $this->assertSame(27, $imageGroups->where('image_count', 4)->count());
        $this->assertSame(108, $imageGroups->where('image_count', 3)->count());
        $this->assertSame(135, $imageGroups->where('cover_count', 1)->count());
        $firstImage = ListingImage::query()->firstOrFail();
        $this->assertStringStartsWith('demo-fixtures/listings/', $firstImage->image_url);
        Storage::disk('public')->assertExists($firstImage->image_url);
        $imageInfo = getimagesizefromstring(Storage::disk('public')->get($firstImage->image_url));
        $this->assertNotFalse($imageInfo);
        $this->assertSame(IMAGETYPE_PNG, $imageInfo[2]);
        $this->assertSame([320, 240], [$imageInfo[0], $imageInfo[1]]);
        $variantHashes = ListingImage::query()->orderBy('id')->limit(8)->get()->map(
            fn (ListingImage $image): string => md5(Storage::disk('public')->get($image->image_url)),
        )->unique();
        $this->assertCount(6, $variantHashes);

        $areaRentBands = [
            'Ở ghép' => [12, 22, 1200000, 2800000],
            'Phòng trọ' => [16, 32, 2200000, 5200000],
            'Căn hộ mini' => [25, 45, 4500000, 8500000],
            'Studio' => [22, 42, 5000000, 9500000],
            'Chung cư' => [45, 85, 7500000, 16000000],
            'Nhà nguyên căn' => [60, 130, 9000000, 22000000],
        ];
        foreach (Listing::query()->with('category')->get() as $listing) {
            if ($listing->category->name === 'Danh mục ẩn (Demo)') {
                continue;
            }
            [$minimumArea, $maximumArea, $minimumRent, $maximumRent] = $areaRentBands[$listing->category->name];
            $this->assertGreaterThanOrEqual($minimumArea, (float) $listing->area_m2);
            $this->assertLessThanOrEqual($maximumArea, (float) $listing->area_m2);
            $this->assertGreaterThanOrEqual($minimumRent, (float) $listing->monthly_rent);
            $this->assertLessThanOrEqual($maximumRent, (float) $listing->monthly_rent);
        }

        $amenityCounts = DB::table('listing_amenities')->join('amenities', 'amenities.id', '=', 'listing_amenities.amenity_id')->select('amenities.name')->selectRaw('COUNT(*) AS listing_count')->groupBy('amenities.name')->pluck('listing_count', 'name')->map(fn ($count): int => (int) $count)->all();
        ksort($amenityCounts);
        $expectedAmenityCounts = ['Wi-Fi' => 108, 'Máy lạnh' => 65, 'Máy giặt' => 47, 'Chỗ để xe' => 97, 'Nội thất' => 54, 'Ban công' => 1];
        ksort($expectedAmenityCounts);
        $this->assertSame($expectedAmenityCounts, $amenityCounts);
        $feeCounts = DB::table('listing_fees')->join('fee_types', 'fee_types.id', '=', 'listing_fees.fee_type_id')->select('fee_types.code')->selectRaw('COUNT(*) AS fee_count')->groupBy('fee_types.code')->pluck('fee_count', 'code')->map(fn ($count): int => (int) $count)->all();
        ksort($feeCounts);
        $this->assertSame(['ELECTRICITY' => 115, 'INTERNET' => 75, 'PARKING' => 70, 'SERVICE' => 45, 'WATER' => 100], $feeCounts);
        $this->assertSame(0, DB::table('listing_fees')->select('listing_id', 'fee_type_id')->groupBy('listing_id', 'fee_type_id')->havingRaw('COUNT(*) > 1')->count());

        $favoriteCounts = DB::table('favorites')->select('user_id')->selectRaw('COUNT(*) AS favorite_count')->groupBy('user_id')->pluck('favorite_count')->map(fn ($count): int => (int) $count)->sort()->values()->all();
        $this->assertSame(array_merge(array_fill(0, 30, 4), array_fill(0, 30, 7), array_fill(0, 10, 10)), $favoriteCounts);
        $this->assertSame(10, DB::table('favorites')->where('user_id', $renter->id)->count());
        $this->assertSame(1, DB::table('favorites')->where('user_id', $renter->id)->whereNotIn('listing_id', $publicListings->modelKeys())->count());
        $this->assertSame(25, DB::table('favorites')->whereNotIn('listing_id', $publicListings->modelKeys())->count());
        foreach (DB::table('favorites')
            ->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->join('listing_moderations as current_moderation', 'current_moderation.id', '=', 'listings.current_moderation_id')
            ->join('users as landlord', 'landlord.id', '=', 'listings.landlord_id')
            ->whereNotIn('favorites.listing_id', $publicListings->modelKeys())
            ->select('favorites.created_at as favorite_created_at', 'listings.updated_at as listing_updated_at', 'listings.expires_at', 'listings.deleted_at', 'current_moderation.reviewed_at', 'landlord.account_status', 'landlord.updated_at as landlord_updated_at')
            ->get() as $favorite) {
            $favoriteCreatedAt = CarbonImmutable::parse($favorite->favorite_created_at, 'UTC');
            $eventAt = match (true) {
                $favorite->expires_at !== null && CarbonImmutable::parse($favorite->expires_at, 'UTC')->lessThanOrEqualTo(now()) => CarbonImmutable::parse($favorite->expires_at, 'UTC'),
                $favorite->deleted_at !== null => CarbonImmutable::parse($favorite->deleted_at, 'UTC'),
                $favorite->account_status === 'LOCKED' => CarbonImmutable::parse($favorite->landlord_updated_at, 'UTC'),
                default => CarbonImmutable::parse($favorite->listing_updated_at, 'UTC'),
            };
            $this->assertTrue($favoriteCreatedAt->greaterThanOrEqualTo(CarbonImmutable::parse($favorite->reviewed_at, 'UTC')));
            $this->assertTrue($favoriteCreatedAt->lessThan($eventAt));
        }

        $slotToday = CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString();
        $this->assertSame(210, DB::table('viewing_slots')->where('viewing_date', '<', $slotToday)->count());
        $this->assertSame(110, DB::table('viewing_slots')->where('viewing_date', '>=', $slotToday)->count());
        $this->assertSame(250, DB::table('viewing_slots')->where('status', 'OPEN')->count());
        $this->assertSame(70, DB::table('viewing_slots')->where('status', 'CLOSED')->count());
        $appointmentCounts = [
            'ACCEPTED' => 22,
            'AUTO_CANCELLED' => 20,
            'CANCELLED' => 30,
            'COMPLETED' => 55,
            'PENDING' => 28,
            'REJECTED' => 25,
        ];
        $actualAppointmentCounts = DB::table('appointments')->select('status')->selectRaw('COUNT(*) AS status_count')->groupBy('status')->pluck('status_count', 'status')->map(fn ($count): int => (int) $count)->all();
        ksort($actualAppointmentCounts);
        ksort($appointmentCounts);
        $this->assertSame($appointmentCounts, $actualAppointmentCounts);
        $this->assertSame(6, DB::table('appointments')->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')->where('appointments.status', 'ACCEPTED')->where('viewing_slots.viewing_date', $slotToday)->count());
        $this->assertSame(0, DB::table('appointments')->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')->join('listings', 'listings.id', '=', 'viewing_slots.listing_id')->whereColumn('appointments.renter_id', 'listings.landlord_id')->count());
        $this->assertSame(0, DB::table('appointments')->whereIn('status', ['PENDING', 'ACCEPTED'])->select('slot_id')->groupBy('slot_id')->havingRaw('COUNT(*) > 1')->count());
        $this->assertSame(0, DB::table('viewing_slots')->select('listing_id', 'viewing_date', 'start_time', 'end_time')->groupBy('listing_id', 'viewing_date', 'start_time', 'end_time')->havingRaw('COUNT(*) > 1')->count());
        foreach (ViewingSlot::query()->where('status', 'OPEN')->orderBy('listing_id')->orderBy('viewing_date')->orderBy('start_time')->get()->groupBy(fn (ViewingSlot $slot): string => $slot->listing_id.'|'.$slot->viewing_date->toDateString()) as $slots) {
            $orderedSlots = $slots->values();
            for ($index = 1; $index < $orderedSlots->count(); $index++) {
                $previous = $orderedSlots[$index - 1];
                $current = $orderedSlots[$index];
                $this->assertFalse($previous->start_time < $current->end_time && $previous->end_time > $current->start_time);
            }
        }

        foreach (DB::table('appointments')
            ->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')
            ->join('listings', 'listings.id', '=', 'viewing_slots.listing_id')
            ->select('appointments.*', 'viewing_slots.viewing_date', 'viewing_slots.start_time', 'viewing_slots.end_time', 'viewing_slots.created_at as slot_created_at', 'listings.created_at as listing_created_at')
            ->get() as $appointment) {
            $start = CarbonImmutable::parse($appointment->viewing_date.' '.$appointment->start_time, ViewingSlot::TIMEZONE)->utc();
            $end = CarbonImmutable::parse($appointment->viewing_date.' '.$appointment->end_time, ViewingSlot::TIMEZONE)->utc();
            $created = CarbonImmutable::parse($appointment->created_at, 'UTC');
            $listingCreated = CarbonImmutable::parse($appointment->listing_created_at, 'UTC');
            $slotCreated = CarbonImmutable::parse($appointment->slot_created_at, 'UTC');
            $this->assertTrue($slotCreated->greaterThanOrEqualTo($listingCreated));
            $this->assertTrue($created->greaterThanOrEqualTo($listingCreated));
            $this->assertTrue($created->lessThan($start));
            if (in_array($appointment->status, ['ACCEPTED', 'REJECTED', 'COMPLETED'], true)) {
                $responded = CarbonImmutable::parse($appointment->responded_at, 'UTC');
                $this->assertTrue($responded->greaterThanOrEqualTo($created));
                $this->assertTrue($responded->lessThan($start));
            }
            if ($appointment->status === 'COMPLETED') {
                $this->assertTrue(CarbonImmutable::parse($appointment->completed_at, 'UTC')->greaterThanOrEqualTo($end));
            }
            if ($appointment->status === 'CANCELLED') {
                $this->assertNotNull($appointment->cancelled_by);
                $this->assertTrue(CarbonImmutable::parse($appointment->cancelled_at, 'UTC')->lessThan($start));
            }
            if ($appointment->status === 'AUTO_CANCELLED') {
                $this->assertNull($appointment->cancelled_by);
                $this->assertSame('VIEWING_TIME_PASSED', $appointment->cancellation_reason);
                $this->assertTrue(CarbonImmutable::parse($appointment->cancelled_at, 'UTC')->greaterThanOrEqualTo($start));
            }
        }

        $reportReasonCounts = DB::table('reports')->join('report_reasons', 'report_reasons.id', '=', 'reports.reason_id')->select('report_reasons.code')->selectRaw('COUNT(*) AS reason_count')->groupBy('report_reasons.code')->pluck('reason_count', 'code')->map(fn ($count): int => (int) $count)->all();
        ksort($reportReasonCounts);
        $this->assertSame(['FRAUD' => 9, 'INAPPROPRIATE_CONTENT' => 8, 'OTHER' => 10, 'WRONG_ADDRESS' => 18, 'WRONG_PRICE' => 15], $reportReasonCounts);
        $reportStatusCounts = DB::table('reports')->select('status')->selectRaw('COUNT(*) AS status_count')->groupBy('status')->pluck('status_count', 'status')->map(fn ($count): int => (int) $count)->all();
        ksort($reportStatusCounts);
        $this->assertSame(['DISMISSED' => 14, 'PENDING' => 18, 'RESOLVED' => 28], $reportStatusCounts);
        $actionCounts = DB::table('enforcement_actions')->select('action_type')->selectRaw('COUNT(*) AS action_count')->groupBy('action_type')->pluck('action_count', 'action_type')->map(fn ($count): int => (int) $count)->all();
        ksort($actionCounts);
        $this->assertSame(['LOCK_ACCOUNT' => 1, 'SUSPEND_LISTING' => 5, 'WARNING' => 22], $actionCounts);
        $this->assertSame(0, DB::table('reports')->join('listings', 'listings.id', '=', 'reports.listing_id')->whereColumn('reports.reporter_id', 'listings.landlord_id')->count());
        $this->assertSame(0, DB::table('reports')->where('status', 'PENDING')->select('reporter_id', 'listing_id')->groupBy('reporter_id', 'listing_id')->havingRaw('COUNT(*) > 1')->count());
        $this->assertDatabaseHas('enforcement_actions', ['action_type' => 'LOCK_ACCOUNT', 'target_user_id' => $lockedLandlord->id]);
        $this->assertSame(5, DB::table('enforcement_actions')->join('listings', 'listings.id', '=', 'enforcement_actions.target_listing_id')->where('enforcement_actions.action_type', 'SUSPEND_LISTING')->where('listings.visibility_status', 'SUSPENDED')->count());
        $this->assertSame(0, DB::table('reports')->whereIn('status', ['DISMISSED', 'RESOLVED'])->whereNull('handled_at')->count());
        $this->assertSame(0, DB::table('reports')->where('status', 'PENDING')->whereNotNull('handled_at')->count());
        foreach (DB::table('reports')->join('listings', 'listings.id', '=', 'reports.listing_id')->select('reports.*', 'listings.created_at as listing_created_at')->get() as $report) {
            $createdAt = CarbonImmutable::parse($report->created_at, 'UTC');
            $this->assertTrue($createdAt->greaterThanOrEqualTo(CarbonImmutable::parse($report->listing_created_at, 'UTC')));
            if ($report->status !== 'PENDING') {
                $this->assertTrue(CarbonImmutable::parse($report->handled_at, 'UTC')->greaterThanOrEqualTo($createdAt));
            }
        }

        $landlordMetrics = app(LandlordDashboardQuery::class)->metrics($landlord);
        $this->assertSame([
            'total_listings' => 15,
            'available_rooms' => 12,
            'rented_rooms' => 3,
            'waiting_appointments' => 4,
            'total_views' => 2340,
        ], $landlordMetrics);
        $adminData = app(AdminDashboardQuery::class)->dashboardData();
        $this->assertSame([
            'total_users' => 120,
            'total_landlords' => 20,
            'pending_reports' => 18,
            'today_appointments' => 6,
        ], $adminData['metrics']);
        $this->assertSame(132, $adminData['listingStates']['total_listings']);
        $this->assertSame(['PENDING' => 19, 'APPROVED' => 104, 'REJECTED' => 9], $adminData['listingStates']['moderation']);
        $this->assertSame(['AVAILABLE' => 111, 'RENTED' => 21], $adminData['listingStates']['occupancy']);
        $this->assertSame(['VISIBLE' => 108, 'HIDDEN' => 16, 'SUSPENDED' => 8], $adminData['listingStates']['visibility']);
        $this->assertCount(5, $adminData['recentPendingModeration']);
        $this->assertCount(5, $adminData['recentPendingReports']);
        $this->assertSame([4, 5, 6, 7, 8, 9, 10, 11, 13, 16, 20, 26], array_column($adminData['monthlyListings'], 'count'));
        $this->assertSame(132, $adminData['categoryDistribution']['total_listings']);
        $this->assertSame(1, collect($adminData['categoryDistribution']['categories'])->firstWhere('name', 'Danh mục ẩn (Demo)')['count']);

        try {
            app(DemoSeeder::class)->run();
            $this->fail('DemoSeeder must refuse a repeated run instead of duplicating or overwriting fixtures.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DemoSeeder requires a fresh, disposable database. Recreate it before seeding demo data.', $exception->getMessage());
        }

        $this->actingAs($renter)
            ->get(route('landlord.listings.create'))
            ->assertOk()
            ->assertSee('TP.HCM')
            ->assertSee('Phường mẫu 03-04')
            ->assertDontSee('Danh mục ẩn (Demo)')
            ->assertDontSee('Ban công');

        $province = DB::table('provinces')->where('code', 'DEMO-P01')->first();
        $district = DB::table('districts')->where('province_id', $province->id)->orderBy('id')->first();
        $ward = DB::table('wards')->where('district_id', $district->id)->orderBy('id')->first();
        $category = RoomCategory::query()->where('name', 'Phòng trọ')->firstOrFail();
        $amenityId = (int) DB::table('amenities')->where('name', 'Wi-Fi')->value('id');
        $this->flushSession();

        $this->actingAs($landlord)
            ->post(route('landlord.listings.store'), [
                'category_id' => $category->id,
                'title' => 'Phòng tạo từ fixture location',
                'description' => 'Kiểm thử form với dữ liệu địa điểm demo.',
                'monthly_rent' => 3500000,
                'deposit_amount' => null,
                'area_m2' => 24,
                'max_occupants' => 2,
                'bedroom_count' => 1,
                'bathroom_count' => 1,
                'gender_requirement' => 'ANY',
                'province_id' => $province->id,
                'district_id' => $district->id,
                'ward_id' => $ward->id,
                'street_address' => '12 Đường Hoa Giấy',
                'amenity_ids' => [$amenityId],
                'fees' => [],
                'images' => [
                    UploadedFile::fake()->image('demo-a.png'),
                    UploadedFile::fake()->image('demo-b.png'),
                    UploadedFile::fake()->image('demo-c.png'),
                ],
                'cover_selection' => 'new:0',
            ])
            ->assertRedirect(route('landlord.listings.index'));

        $this->assertDatabaseHas('listings', ['title' => 'Phòng tạo từ fixture location', 'ward_id' => $ward->id]);
        $this->assertDatabaseCount('listings', 136);
    }

    public function test_demo_seeder_keeps_chart_and_booking_dates_coherent_on_the_first_day_of_a_month(): void
    {
        Storage::fake('public');
        $frozenNow = Carbon::parse('2026-10-01 00:15:00', 'UTC');
        Carbon::setTestNow($frozenNow);
        CarbonImmutable::setTestNow($frozenNow->toImmutable());
        $this->assertSame('2026-10', CarbonImmutable::now('UTC')->format('Y-m'));
        $this->seed(DatabaseSeeder::class);

        app(DemoSeeder::class)->run();

        $adminData = app(AdminDashboardQuery::class)->dashboardData();
        $this->assertSame([4, 5, 6, 7, 8, 9, 10, 11, 13, 16, 20, 26], array_column($adminData['monthlyListings'], 'count'));
        $this->assertSame(80, app(ListingSearchQuery::class)->build()->count());
        $today = CarbonImmutable::now(ViewingSlot::TIMEZONE)->toDateString();
        $this->assertSame(6, DB::table('appointments')
            ->join('viewing_slots', 'viewing_slots.id', '=', 'appointments.slot_id')
            ->where('appointments.status', 'ACCEPTED')
            ->where('viewing_slots.viewing_date', $today)
            ->count());
        $this->assertSame(430, DB::table('favorites')->count());
        $this->assertSame(432, DB::table('listing_images')->count());
    }

    public function test_demo_seeder_refuses_production_and_non_empty_databases(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            app(DemoSeeder::class)->run();
            $this->fail('DemoSeeder must refuse production.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DemoSeeder is disabled in the production environment.', $exception->getMessage());
        }

        $this->app->detectEnvironment(fn (): string => 'testing');
        User::factory()->create();

        try {
            app(DemoSeeder::class)->run();
            $this->fail('DemoSeeder must refuse a database with application data.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DemoSeeder requires a fresh, disposable database. Recreate it before seeding demo data.', $exception->getMessage());
        }

        $this->assertDatabaseCount('provinces', 0);
        $this->assertDatabaseCount('amenities', 0);
        $this->assertDatabaseCount('listings', 0);
    }
}
