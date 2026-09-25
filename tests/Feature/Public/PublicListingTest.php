<?php

namespace Tests\Feature\Public;

use App\Models\Amenity;
use App\Models\District;
use App\Models\FeeType;
use App\Models\FeeUnit;
use App\Models\Listing;
use App\Models\Province;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicListingTest extends TestCase
{
    use RefreshDatabase;

    private User $landlord;

    private RoomCategory $category;

    private Province $province;

    private District $district;

    private Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->landlord = User::factory()->create([
            'email' => 'private-account@example.test',
            'phone' => '0901234567',
        ]);
        $profile = $this->landlord->profile()->create([
            'full_name' => 'Chủ trọ công khai',
            'contact_address' => 'Địa chỉ riêng tư 12',
            'zalo_number' => '0907654321',
        ]);
        $profile->forceFill(['avatar_url' => 'images/landlord.png'])->save();
        $this->category = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true]);
        [$this->province, $this->district, $this->ward] = $this->createLocation('HN', 'CG', 'DV');
    }

    public function test_guest_and_authenticated_users_can_open_public_listing_index(): void
    {
        $this->createListing('Tin công khai');

        $this->get('/rooms')->assertOk()->assertSee('Tin công khai');
        $this->actingAs(User::factory()->create())
            ->get('/rooms')
            ->assertOk()
            ->assertSee('Tin công khai');
    }

    public function test_index_requires_all_public_eligibility_conditions(): void
    {
        $eligible = $this->createListing('Được hiển thị');
        $this->createListing('Chờ duyệt', ['moderation_status' => 'PENDING']);
        $this->createListing('Bị từ chối', ['moderation_status' => 'REJECTED']);
        $this->createListing('Đang ẩn', ['visibility_status' => 'HIDDEN']);
        $this->createListing('Bị tạm ngưng', ['visibility_status' => 'SUSPENDED']);
        $this->createListing('Đã thuê', ['occupancy_status' => 'RENTED']);
        $this->createListing('Đã xóa', ['deleted_at' => now()]);
        $this->createListing('Hết hạn đã lưu', ['expires_at' => now()->subSecond()]);
        $this->createListing('Hết hạn theo ngày duyệt', ['reviewed_at' => now()->subDays(31)]);

        $this->get('/rooms')
            ->assertOk()
            ->assertSee('Được hiển thị')
            ->assertDontSee('Chờ duyệt')
            ->assertDontSee('Bị từ chối')
            ->assertDontSee('Đang ẩn')
            ->assertDontSee('Bị tạm ngưng')
            ->assertDontSee('Đã thuê')
            ->assertDontSee('Đã xóa')
            ->assertDontSee('Hết hạn đã lưu')
            ->assertDontSee('Hết hạn theo ngày duyệt');

        $this->assertSame('APPROVED', $eligible->currentModeration->status);
    }

    public function test_expiry_boundary_is_exclusive_for_persisted_and_legacy_expiry(): void
    {
        $boundary = now()->startOfSecond();
        $this->travelTo($boundary);
        $beforeBoundary = $this->createListing('Còn hạn ngay trước mốc', [
            'expires_at' => $boundary->copy()->addSecond(),
        ]);
        $atBoundary = $this->createListing('Hết hạn đúng mốc', [
            'expires_at' => $boundary,
        ]);
        $legacyAtBoundary = $this->createListing('Hết hạn theo duyệt đúng mốc', [
            'expires_at' => null,
            'reviewed_at' => $boundary->copy()->subDays(30),
        ]);

        $this->get('/rooms')
            ->assertOk()
            ->assertSee('Còn hạn ngay trước mốc')
            ->assertDontSee('Hết hạn đúng mốc')
            ->assertDontSee('Hết hạn theo duyệt đúng mốc');

        $this->get(route('public.listings.show', $atBoundary))->assertNotFound();
        $this->get(route('public.listings.show', $legacyAtBoundary))->assertNotFound();
        $this->assertNull($beforeBoundary->fresh()->deleted_at);
    }

    public function test_prior_approved_moderation_does_not_qualify_a_current_pending_listing(): void
    {
        $listing = $this->createListing('Phiên hiện tại đang chờ', ['moderation_status' => 'APPROVED']);
        $listing->moderations()->create([
            'version_no' => 2,
            'status' => 'PENDING',
            'submitted_at' => now(),
        ]);
        $listing->forceFill(['current_moderation_id' => $listing->moderations()->latest('id')->value('id')])->save();

        $this->get('/rooms')->assertOk()->assertDontSee('Phiên hiện tại đang chờ');
    }

    public function test_detail_is_available_only_for_publicly_eligible_listings_and_increments_views(): void
    {
        $listing = $this->createListing('Chi tiết công khai', ['view_count' => 7]);
        $updatedAt = $listing->fresh()->updated_at->toDateTimeString();
        $amenity = Amenity::query()->create(['name' => 'Wi-Fi công khai', 'is_active' => true]);
        $listing->amenities()->attach($amenity->id);
        $feeType = FeeType::query()->create(['code' => 'ELECTRICITY', 'name' => 'Tiền điện', 'is_active' => true]);
        $feeUnit = FeeUnit::query()->create(['code' => 'PER_KWH', 'name' => 'mỗi kWh', 'is_active' => true]);
        $listing->fees()->create([
            'fee_type_id' => $feeType->id,
            'fee_unit_id' => $feeUnit->id,
            'amount' => 3500,
            'note' => 'Theo công tơ',
        ]);

        $this->travel(5)->seconds();
        $response = $this->get('/rooms/'.$listing->id);
        $response->assertOk()
            ->assertSee('Chi tiết công khai')
            ->assertSee('Mô tả căn phòng')
            ->assertSee('Phòng trọ')
            ->assertSee('2.500.000')
            ->assertSee('1.000.000')
            ->assertSee('22,00')
            ->assertSee('Dịch Vọng')
            ->assertSee('Wi-Fi công khai')
            ->assertSee('Tiền điện')
            ->assertSee('3.500')
            ->assertSee('Theo công tơ')
            ->assertSee('Chủ trọ công khai')
            ->assertSee('images/landlord.png')
            ->assertSee('0901234567')
            ->assertSee('0907654321')
            ->assertDontSee('private-account@example.test')
            ->assertDontSee('Địa chỉ riêng tư 12')
            ->assertDontSee('landlord_id')
            ->assertDontSee('account_status')
            ->assertDontSee('APPROVED')
            ->assertDontSee('PENDING');

        $shownListing = $response->viewData('listing');
        $this->assertTrue($shownListing->relationLoaded('category'));
        $this->assertTrue($shownListing->relationLoaded('ward'));
        $this->assertTrue($shownListing->ward->relationLoaded('district'));
        $this->assertTrue($shownListing->relationLoaded('amenities'));
        $this->assertTrue($shownListing->relationLoaded('fees'));
        $this->assertTrue($shownListing->relationLoaded('images'));
        $this->assertTrue($shownListing->landlord->relationLoaded('profile'));
        $this->assertFalse($shownListing->relationLoaded('currentModeration'));
        $this->assertFalse($shownListing->relationLoaded('moderations'));
        $this->assertSame(8, (int) $listing->fresh()->view_count);
        $this->assertSame($updatedAt, $listing->fresh()->updated_at->toDateTimeString());

        $this->actingAs(User::factory()->create())
            ->get('/rooms/'.$listing->id)
            ->assertOk();

        $this->assertSame(9, (int) $listing->fresh()->view_count);
        $this->assertSame($updatedAt, $listing->fresh()->updated_at->toDateTimeString());
    }

    public function test_index_shows_a_clear_empty_state_when_no_public_rooms_match(): void
    {
        $response = $this->get('/rooms?q=no-matching-room')->assertOk();

        $response->assertSee(__('ui.public_listings.empty_heading'))
            ->assertSee(__('ui.public_listings.clear_filters'))
            ->assertSee(__('ui.public_listings.results_count', ['count' => '0']));
        $this->assertSame(0, $response->viewData('listings')->total());
    }

    public function test_non_public_detail_returns_generic_404_without_incrementing_views(): void
    {
        $listings = [
            $this->createListing('Không công khai đang chờ', ['moderation_status' => 'PENDING', 'view_count' => 4]),
            $this->createListing('Không công khai bị từ chối', ['moderation_status' => 'REJECTED', 'view_count' => 4]),
            $this->createListing('Không công khai ẩn', ['visibility_status' => 'HIDDEN', 'view_count' => 4]),
            $this->createListing('Không công khai tạm ngưng', ['visibility_status' => 'SUSPENDED', 'view_count' => 4]),
            $this->createListing('Không công khai đã thuê', ['occupancy_status' => 'RENTED', 'view_count' => 4]),
            $this->createListing('Không công khai đã xóa', ['deleted_at' => now(), 'view_count' => 4]),
            $this->createListing('Không công khai hết hạn', ['expires_at' => now()->subDay(), 'view_count' => 4]),
        ];

        foreach ($listings as $listing) {
            $this->get('/rooms/'.$listing->id)
                ->assertNotFound()
                ->assertDontSee($listing->title)
                ->assertDontSee('Lý do kiểm duyệt bí mật');
            $this->assertSame(4, (int) $listing->fresh()->view_count);
        }
    }

    public function test_keyword_search_matches_title_and_street_address_only(): void
    {
        $this->createListing('Phòng An Bình');
        $this->createListing('Tin khác', ['street_address' => '24 Đường An Bình']);
        $this->createListing('Không tìm mô tả', ['description' => 'Nội dung An Bình không được tìm']);

        $this->get('/rooms?q=An%20B%C3%ACnh')
            ->assertOk()
            ->assertSee('Phòng An Bình')
            ->assertSee('Tin khác')
            ->assertDontSee('Không tìm mô tả');
    }

    public function test_location_filters_follow_province_district_and_ward_relationships(): void
    {
        [$province, $district, $ward] = $this->createLocation('HCM', 'Q1', 'BT');
        $this->createListing('Hà Nội');
        $this->createListing('Hồ Chí Minh', ['ward_id' => $ward->id]);

        foreach ([
            ['province_id' => $province->id],
            ['province_id' => $province->id, 'district_id' => $district->id],
            ['province_id' => $province->id, 'district_id' => $district->id, 'ward_id' => $ward->id],
        ] as $filters) {
            $response = $this->get('/rooms?'.http_build_query($filters))->assertOk();
            $this->assertSame(['Hồ Chí Minh'], $response->viewData('listings')->getCollection()->pluck('title')->all());
        }
    }

    public function test_invalid_location_hierarchy_is_rejected(): void
    {
        [$otherProvince, $otherDistrict, $otherWard] = $this->createLocation('DN', 'HC', 'HP');

        $this->from('/rooms')->get('/rooms?'.http_build_query([
            'province_id' => $this->province->id,
            'district_id' => $otherDistrict->id,
        ]))->assertRedirect('/rooms')->assertSessionHasErrors('district_id');

        $this->from('/rooms')->get('/rooms?'.http_build_query([
            'province_id' => $otherProvince->id,
            'district_id' => $otherDistrict->id,
            'ward_id' => $this->ward->id,
        ]))->assertRedirect('/rooms')->assertSessionHasErrors('ward_id');
    }

    public function test_price_and_area_ranges_support_minimum_maximum_and_combined_bounds(): void
    {
        $this->createListing('Low range', ['monthly_rent' => 1_000_000, 'area_m2' => 10]);
        $this->createListing('Mid range', ['monthly_rent' => 2_000_000, 'area_m2' => 20]);
        $this->createListing('High range', ['monthly_rent' => 3_000_000, 'area_m2' => 30]);

        $this->get('/rooms?min_price=2000000')->assertOk()->assertSee('Mid range')->assertSee('High range')->assertDontSee('Low range');
        $this->get('/rooms?max_price=2000000')->assertOk()->assertSee('Low range')->assertSee('Mid range')->assertDontSee('High range');
        $this->get('/rooms?min_price=1500000&max_price=2500000')->assertOk()->assertSee('Mid range')->assertDontSee('Low range')->assertDontSee('High range');
        $this->get('/rooms?min_area=20')->assertOk()->assertSee('Mid range')->assertSee('High range')->assertDontSee('Low range');
        $this->get('/rooms?max_area=20')->assertOk()->assertSee('Low range')->assertSee('Mid range')->assertDontSee('High range');
        $this->get('/rooms?min_area=15&max_area=25')->assertOk()->assertSee('Mid range')->assertDontSee('Low range')->assertDontSee('High range');
    }

    public function test_gender_filter_matches_suitable_any_listings(): void
    {
        $this->createListing('Chỉ nam', ['gender_requirement' => 'MALE']);
        $this->createListing('Chỉ nữ', ['gender_requirement' => 'FEMALE']);
        $this->createListing('Không giới hạn', ['gender_requirement' => 'ANY']);

        $this->get('/rooms?gender_requirement=MALE')->assertOk()->assertSee('Chỉ nam')->assertSee('Không giới hạn')->assertDontSee('Chỉ nữ');
        $this->get('/rooms?gender_requirement=FEMALE')->assertOk()->assertSee('Chỉ nữ')->assertSee('Không giới hạn')->assertDontSee('Chỉ nam');
        $this->get('/rooms?gender_requirement=ANY')->assertOk()->assertSee('Không giới hạn')->assertDontSee('Chỉ nam')->assertDontSee('Chỉ nữ');
    }

    public function test_amenity_filters_require_all_selected_amenities_without_duplicate_rows(): void
    {
        $wifi = Amenity::query()->create(['name' => 'Wi-Fi', 'is_active' => true]);
        $parking = Amenity::query()->create(['name' => 'Chỗ để xe', 'is_active' => true]);
        $wifiOnly = $this->createListing('Wi-Fi');
        $wifiOnly->amenities()->attach($wifi->id);
        $both = $this->createListing('Đủ tiện nghi');
        $both->amenities()->attach([$wifi->id, $parking->id]);
        $parkingOnly = $this->createListing('Chỉ để xe');
        $parkingOnly->amenities()->attach($parking->id);

        $oneAmenityResponse = $this->get('/rooms?'.http_build_query(['amenity_ids' => [$wifi->id]]))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Wi-Fi', 'Đủ tiện nghi'],
            $oneAmenityResponse->viewData('listings')->getCollection()->pluck('title')->all(),
        );

        $response = $this->get('/rooms?'.http_build_query(['amenity_ids' => [$wifi->id, $parking->id]]));
        $response->assertOk();
        $listings = $response->viewData('listings');
        $this->assertSame(1, $listings->total());
        $this->assertSame(['Đủ tiện nghi'], $listings->getCollection()->pluck('title')->all());
        $this->assertCount(1, $listings->getCollection()->pluck('id')->unique());
        $this->assertSame(1, $wifiOnly->amenities()->count());
    }

    public function test_combined_search_filters_are_applied_together(): void
    {
        $amenity = Amenity::query()->create(['name' => 'Máy giặt', 'is_active' => true]);
        $matching = $this->createListing('Phòng ở Hà Nội', [
            'monthly_rent' => 2_500_000,
            'area_m2' => 22,
            'gender_requirement' => 'ANY',
        ]);
        $matching->amenities()->attach($amenity->id);
        $this->createListing('Sai giá', ['monthly_rent' => 5_000_000, 'area_m2' => 22, 'gender_requirement' => 'ANY']);

        $response = $this->get('/rooms?'.http_build_query([
            'q' => 'Hà Nội',
            'province_id' => $this->province->id,
            'district_id' => $this->district->id,
            'ward_id' => $this->ward->id,
            'min_price' => 2_000_000,
            'max_price' => 3_000_000,
            'min_area' => 20,
            'max_area' => 25,
            'amenity_ids' => [$amenity->id],
            'gender_requirement' => 'MALE',
            'sort' => 'price_asc',
        ]));

        $response->assertOk()->assertSee('Phòng ở Hà Nội')->assertDontSee('Sai giá');
        $this->assertSame(1, $response->viewData('listings')->total());
    }

    public function test_all_six_sort_modes_have_the_confirmed_ordering(): void
    {
        $this->createListing('Cũ giá vừa diện tích vừa', [
            'created_at' => now()->subDays(2),
            'monthly_rent' => 2_000_000,
            'area_m2' => 20,
            'view_count' => 20,
        ]);
        $this->createListing('Mới giá thấp diện tích nhỏ', [
            'created_at' => now()->subDay(),
            'monthly_rent' => 1_000_000,
            'area_m2' => 10,
            'view_count' => 5,
        ]);
        $this->createListing('Cũ nhất giá cao diện tích lớn', [
            'created_at' => now()->subDays(3),
            'monthly_rent' => 3_000_000,
            'area_m2' => 30,
            'view_count' => 50,
        ]);

        $expected = [
            'newest' => ['Mới giá thấp diện tích nhỏ', 'Cũ giá vừa diện tích vừa', 'Cũ nhất giá cao diện tích lớn'],
            'price_asc' => ['Mới giá thấp diện tích nhỏ', 'Cũ giá vừa diện tích vừa', 'Cũ nhất giá cao diện tích lớn'],
            'price_desc' => ['Cũ nhất giá cao diện tích lớn', 'Cũ giá vừa diện tích vừa', 'Mới giá thấp diện tích nhỏ'],
            'area_asc' => ['Mới giá thấp diện tích nhỏ', 'Cũ giá vừa diện tích vừa', 'Cũ nhất giá cao diện tích lớn'],
            'area_desc' => ['Cũ nhất giá cao diện tích lớn', 'Cũ giá vừa diện tích vừa', 'Mới giá thấp diện tích nhỏ'],
            'most_viewed' => ['Cũ nhất giá cao diện tích lớn', 'Cũ giá vừa diện tích vừa', 'Mới giá thấp diện tích nhỏ'],
        ];

        foreach ($expected as $mode => $titles) {
            $response = $this->get('/rooms?sort='.$mode)->assertOk();
            $this->assertSame($titles, $response->viewData('listings')->getCollection()->pluck('title')->all(), $mode);
        }
    }

    public function test_all_sort_modes_use_descending_id_as_stable_tie_breaker(): void
    {
        $sameDate = now()->startOfSecond();
        $first = $this->createListing('Tạo trước', [
            'created_at' => $sameDate,
            'monthly_rent' => 2_000_000,
            'area_m2' => 20,
            'view_count' => 5,
        ]);
        $second = $this->createListing('Tạo sau', [
            'created_at' => $sameDate,
            'monthly_rent' => 2_000_000,
            'area_m2' => 20,
            'view_count' => 5,
        ]);

        foreach (['newest', 'price_asc', 'price_desc', 'area_asc', 'area_desc', 'most_viewed'] as $sort) {
            $response = $this->get('/rooms?sort='.$sort)->assertOk();
            $this->assertSame([$second->id, $first->id], $response->viewData('listings')->getCollection()->pluck('id')->all(), $sort);
        }
    }

    public function test_invalid_query_values_and_reversed_ranges_return_field_errors(): void
    {
        $this->from('/rooms')->get('/rooms?province_id=not-an-id')->assertRedirect('/rooms')->assertSessionHasErrors('province_id');
        $this->from('/rooms')->get('/rooms?gender_requirement=OTHER')->assertRedirect('/rooms')->assertSessionHasErrors('gender_requirement');
        $this->from('/rooms')->get('/rooms?sort=monthly_rent')->assertRedirect('/rooms')->assertSessionHasErrors('sort');
        $this->from('/rooms')->get('/rooms?min_price=3000000&max_price=2000000')->assertRedirect('/rooms')->assertSessionHasErrors('min_price');
        $this->from('/rooms')->get('/rooms?min_area=30&max_area=20')->assertRedirect('/rooms')->assertSessionHasErrors('min_area');
        $this->from('/rooms')->get('/rooms?amenity_ids[]=invalid')->assertRedirect('/rooms')->assertSessionHasErrors('amenity_ids.0');

        $inactiveProvince = Province::query()->create(['code' => 'OFF', 'name' => 'Ngừng hoạt động', 'is_active' => false]);
        $inactiveAmenity = Amenity::query()->create(['name' => 'Tiện nghi ngừng hoạt động', 'is_active' => false]);
        $this->from('/rooms')->get('/rooms?province_id='.$inactiveProvince->id)->assertRedirect('/rooms')->assertSessionHasErrors('province_id');
        $this->from('/rooms')->get('/rooms?amenity_ids[]='.$inactiveAmenity->id)->assertRedirect('/rooms')->assertSessionHasErrors('amenity_ids.0');
    }

    public function test_pagination_preserves_filters_and_has_no_duplicate_ids(): void
    {
        foreach (range(1, 11) as $number) {
            $this->createListing('Trang phòng '.$number, ['monthly_rent' => 2_000_000 + $number]);
        }

        $query = http_build_query(['q' => 'Trang phòng', 'min_price' => 2_000_000, 'sort' => 'price_asc']);
        $firstResponse = $this->get('/rooms?'.$query)->assertOk();
        $firstPage = $firstResponse->viewData('listings');
        $this->assertSame(11, $firstPage->total());
        $this->assertStringContainsString('q=Trang%20ph%C3%B2ng', $firstPage->nextPageUrl());
        $this->assertStringContainsString('min_price=2000000', $firstPage->nextPageUrl());
        $this->assertStringContainsString('sort=price_asc', $firstPage->nextPageUrl());

        $secondResponse = $this->get($firstPage->nextPageUrl())->assertOk();
        $secondPage = $secondResponse->viewData('listings');
        $allIds = $firstPage->getCollection()->pluck('id')->merge($secondPage->getCollection()->pluck('id'));
        $this->assertCount(11, $allIds->unique());
        $this->assertSame(2, $secondPage->currentPage());
    }

    public function test_index_eager_loads_only_public_card_relationships(): void
    {
        $this->createListing('Eager room');

        $response = $this->get('/rooms')->assertOk();
        $listing = $response->viewData('listings')->first();

        $this->assertTrue($listing->relationLoaded('category'));
        $this->assertTrue($listing->relationLoaded('coverImage'));
        $this->assertTrue($listing->relationLoaded('ward'));
        $this->assertTrue($listing->ward->relationLoaded('district'));
        $this->assertTrue($listing->ward->district->relationLoaded('province'));
        $this->assertTrue($listing->relationLoaded('amenities'));
        $this->assertFalse($listing->relationLoaded('images'));
        $this->assertFalse($listing->relationLoaded('currentModeration'));
        $this->assertFalse($listing->relationLoaded('moderations'));
    }

    private function createListing(string $title, array $overrides = []): Listing
    {
        $moderationStatus = $overrides['moderation_status'] ?? 'APPROVED';
        $reviewedAt = $overrides['reviewed_at'] ?? now()->subDay();
        unset($overrides['moderation_status'], $overrides['reviewed_at']);

        $listing = new Listing;
        $listing->forceFill(array_merge([
            'landlord_id' => $this->landlord->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'description' => 'Mô tả căn phòng',
            'monthly_rent' => 2_500_000,
            'deposit_amount' => 1_000_000,
            'area_m2' => 22,
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
            'reviewed_by' => $moderationStatus === 'PENDING' ? null : $this->landlord->id,
            'rejection_reason' => $moderationStatus === 'REJECTED' ? 'Lý do kiểm duyệt bí mật' : null,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => $moderationStatus === 'PENDING' ? null : $reviewedAt,
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
        $listing->images()->create([
            'image_url' => 'listings/'.$listing->id.'/cover.jpg',
            'is_cover' => true,
            'display_order' => 0,
            'created_at' => now(),
        ]);

        return $listing->fresh(['currentModeration']);
    }

    /** @return array{Province, District, Ward} */
    private function createLocation(string $provinceCode, string $districtCode, string $wardCode): array
    {
        $province = Province::query()->create([
            'code' => $provinceCode,
            'name' => match ($provinceCode) {
                'HCM' => 'Hồ Chí Minh', 'DN' => 'Đà Nẵng', default => 'Hà Nội'
            },
            'is_active' => true,
        ]);
        $district = District::query()->create([
            'province_id' => $province->id,
            'code' => $districtCode,
            'name' => match ($districtCode) {
                'Q1' => 'Quận 1', 'HC' => 'Hải Châu', default => 'Cầu Giấy'
            },
            'is_active' => true,
        ]);
        $ward = Ward::query()->create([
            'district_id' => $district->id,
            'code' => $wardCode,
            'name' => match ($wardCode) {
                'BT' => 'Bến Thành', 'HP' => 'Hải Phòng', default => 'Dịch Vọng'
            },
            'is_active' => true,
        ]);

        return [$province, $district, $ward];
    }
}
