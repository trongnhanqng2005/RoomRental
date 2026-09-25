<?php

namespace Tests\Feature\Renter;

use App\Models\District;
use App\Models\Listing;
use App\Models\Province;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class FavoritesWishlistTest extends TestCase
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

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->landlord = $this->userWithRoles('LANDLORD');
        $this->category = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true]);
        $this->province = Province::query()->create(['code' => 'HN', 'name' => 'Hà Nội', 'is_active' => true]);
        $this->district = District::query()->create([
            'province_id' => $this->province->id,
            'code' => 'CG',
            'name' => 'Cầu Giấy',
            'is_active' => true,
        ]);
        $this->ward = Ward::query()->create([
            'district_id' => $this->district->id,
            'code' => 'DV',
            'name' => 'Dịch Vọng',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_wishlist_or_mutate_favorites(): void
    {
        $listing = $this->createListing('Phòng công khai');

        $this->get(route('wishlist.index'))->assertRedirect('/login');
        $this->post(route('favorites.store', $listing))->assertRedirect('/login');
        $this->delete(route('favorites.destroy', $listing))->assertRedirect('/login');

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_admin_and_landlord_without_renter_role_are_denied_wishlist_and_favorite_actions(): void
    {
        $listing = $this->createListing('Phòng công khai');

        foreach (['ADMIN', 'SUPER_ADMIN', 'LANDLORD'] as $role) {
            $user = $this->userWithRoles($role);

            $this->actingAs($user)->get(route('wishlist.index'))->assertForbidden();
            $this->actingAs($user)->post(route('favorites.store', $listing))->assertForbidden();
            $this->actingAs($user)->delete(route('favorites.destroy', $listing))->assertForbidden();
        }

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_renter_and_renter_landlord_can_access_wishlist(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $landlord = $this->userWithRoles('RENTER', 'LANDLORD');

        $this->actingAs($renter)->get(route('wishlist.index'))->assertOk();
        $this->actingAs($landlord)->get(route('wishlist.index'))->assertOk();
    }

    public function test_renter_can_favorite_public_listing_without_trusting_client_user_id(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $otherUser = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Phòng công khai');

        $this->actingAs($renter)
            ->from('/rooms')
            ->post(route('favorites.store', $listing), ['user_id' => $otherUser->id])
            ->assertRedirect('/rooms');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $renter->id,
            'listing_id' => $listing->id,
        ]);
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $otherUser->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_repeated_favorite_requests_are_idempotent_under_the_composite_key(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Phòng công khai');

        $this->actingAs($renter)->post(route('favorites.store', $listing));
        $this->actingAs($renter)->post(route('favorites.store', $listing));

        $this->assertDatabaseCount('favorites', 1);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $renter->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_non_public_listing_cannot_be_favorited(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listings = [
            $this->createListing('Đang ẩn', ['visibility_status' => 'HIDDEN']),
            $this->createListing('Tạm ngưng', ['visibility_status' => 'SUSPENDED']),
            $this->createListing('Chờ duyệt', ['moderation_status' => 'PENDING']),
            $this->createListing('Bị từ chối', ['moderation_status' => 'REJECTED']),
            $this->createListing('Đã thuê', ['occupancy_status' => 'RENTED']),
            $this->createListing('Đã hết hạn', ['expires_at' => now()->subSecond()]),
            $this->createListing('Đã xóa', ['deleted_at' => now()]),
        ];

        foreach ($listings as $listing) {
            $this->actingAs($renter)
                ->post(route('favorites.store', $listing))
                ->assertNotFound();
        }

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_unrelated_database_failure_during_favorite_insert_is_rethrown(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Phòng công khai');
        $exception = new QueryException(
            'mysql',
            'insert into `favorites` (`user_id`, `listing_id`, `created_at`) values (?, ?, ?)',
            [],
            new PDOException('Forced database failure', 1644),
        );

        DB::shouldReceive('table')
            ->once()
            ->with('favorites')
            ->andThrow($exception);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->actingAs($renter)->post(route('favorites.store', $listing));
    }

    public function test_renter_can_remove_own_favorite_without_affecting_another_users_favorite(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $otherRenter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Phòng công khai');
        $renter->favorites()->attach($listing->id, ['created_at' => now()]);
        $otherRenter->favorites()->attach($listing->id, ['created_at' => now()]);

        $this->actingAs($renter)->delete(route('favorites.destroy', $listing))->assertRedirect();
        $this->actingAs($renter)->delete(route('favorites.destroy', $listing))->assertRedirect();

        $this->assertDatabaseMissing('favorites', ['user_id' => $renter->id, 'listing_id' => $listing->id]);
        $this->assertDatabaseHas('favorites', ['user_id' => $otherRenter->id, 'listing_id' => $listing->id]);
    }

    public function test_wishlist_is_private_ordered_and_paginated_without_duplicate_listings(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $otherRenter = $this->userWithRoles('RENTER');
        $listings = [];

        foreach (range(1, 11) as $number) {
            $listings[] = $this->createListing('Tin đã lưu '.$number);
        }

        foreach ($listings as $index => $listing) {
            $renter->favorites()->attach($listing->id, ['created_at' => now()->subMinutes($index)]);
        }
        $otherListing = $this->createListing('Tin riêng của người khác');
        $otherRenter->favorites()->attach($otherListing->id, ['created_at' => now()]);

        $firstPage = $this->actingAs($renter)->get(route('wishlist.index'))->assertOk();
        $firstFavorites = $firstPage->viewData('favorites');
        $this->assertSame(11, $firstFavorites->total());
        $this->assertCount(10, $firstFavorites->items());
        $this->assertSame('Tin đã lưu 1', $firstFavorites->first()->title);
        $this->assertCount(10, $firstFavorites->getCollection()->pluck('id')->unique());
        $firstPage->assertDontSee('Tin riêng của người khác');

        $secondPage = $this->get(route('wishlist.index', ['page' => 2]))->assertOk();
        $this->assertSame(2, $secondPage->viewData('favorites')->currentPage());
        $this->assertCount(1, $secondPage->viewData('favorites')->items());
        $this->assertSame('Tin đã lưu 11', $secondPage->viewData('favorites')->first()->title);
    }

    public function test_wishlist_empty_state_is_rendered(): void
    {
        $renter = $this->userWithRoles('RENTER');

        $this->actingAs($renter)
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.empty_heading'))
            ->assertSee(__('ui.favorites.browse_rooms'));
    }

    public function test_removing_the_last_item_on_a_page_redirects_to_the_last_non_empty_page(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listings = [];

        foreach (range(1, 11) as $number) {
            $listings[] = $this->createListing('Tin trang '.$number);
        }

        foreach ($listings as $index => $listing) {
            $renter->favorites()->attach($listing->id, ['created_at' => now()->subMinutes($index)]);
        }

        $this->actingAs($renter)
            ->from(route('wishlist.index', ['page' => 2]))
            ->delete(route('favorites.destroy', $listings[10]))
            ->assertRedirect(route('wishlist.index', ['page' => 2]));

        $this->get(route('wishlist.index', ['page' => 2]))
            ->assertRedirect(route('wishlist.index'));

        $this->get(route('wishlist.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.saved_count', ['count' => '10']));
    }

    public function test_favorites_remain_when_listings_become_non_public_and_are_removable(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listings = [
            $this->createListing('Đang ẩn', ['visibility_status' => 'HIDDEN']),
            $this->createListing('Tạm ngưng', ['visibility_status' => 'SUSPENDED']),
            $this->createListing('Chờ duyệt', ['moderation_status' => 'PENDING']),
            $this->createListing('Bị từ chối', ['moderation_status' => 'REJECTED']),
            $this->createListing('Đã thuê', ['occupancy_status' => 'RENTED']),
            $this->createListing('Đã hết hạn', ['expires_at' => now()->subSecond()]),
            $this->createListing('Đã xóa', ['deleted_at' => now()]),
        ];

        foreach ($listings as $listing) {
            $renter->favorites()->attach($listing->id, ['created_at' => now()]);
        }

        $response = $this->actingAs($renter)->get(route('wishlist.index'))->assertOk();
        $this->assertSame(count($listings), $response->viewData('favorites')->total());
        $response->assertSee(__('ui.favorites.unavailable'));

        foreach ($listings as $listing) {
            $this->assertDatabaseHas('favorites', ['user_id' => $renter->id, 'listing_id' => $listing->id]);
            $response->assertDontSee(route('public.listings.show', $listing));
        }

        $this->actingAs($renter)->delete(route('favorites.destroy', $listings[0]))->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['user_id' => $renter->id, 'listing_id' => $listings[0]->id]);
        $this->assertDatabaseCount('favorites', count($listings) - 1);
    }

    public function test_favorite_of_locked_landlord_listing_remains_as_generic_unavailable_and_can_be_removed(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Tin của chủ trọ đã khóa');
        $renter->favorites()->attach($listing->id, ['created_at' => now()]);
        $this->landlord->forceFill(['account_status' => 'LOCKED'])->save();

        $response = $this->actingAs($renter)->get(route('wishlist.index'))->assertOk();
        $response->assertSee(__('ui.favorites.unavailable'))
            ->assertDontSee('Tin của chủ trọ đã khóa')
            ->assertDontSee(route('public.listings.show', $listing));
        $this->assertDatabaseHas('favorites', ['user_id' => $renter->id, 'listing_id' => $listing->id]);

        $this->actingAs($renter)->delete(route('favorites.destroy', $listing))->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['user_id' => $renter->id, 'listing_id' => $listing->id]);
    }

    public function test_unavailable_wishlist_item_does_not_expose_listing_or_moderation_content_and_public_detail_is_404(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Nội dung riêng tư hiện tại', [
            'moderation_status' => 'REJECTED',
            'rejection_reason' => 'Lý do kiểm duyệt không được hiển thị',
            'monthly_rent' => 9876543,
            'street_address' => 'Địa chỉ nội bộ 123',
        ]);
        $listing->images()->delete();
        $listing->images()->create([
            'image_url' => 'private/current-cover.jpg',
            'is_cover' => true,
            'display_order' => 0,
            'created_at' => now(),
        ]);
        $renter->favorites()->attach($listing->id, ['created_at' => now()]);

        $this->actingAs($renter)
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.unavailable'))
            ->assertDontSee($listing->title)
            ->assertDontSee('Lý do kiểm duyệt không được hiển thị')
            ->assertDontSee('9.876.543')
            ->assertDontSee('Địa chỉ nội bộ 123')
            ->assertDontSee('private/current-cover.jpg')
            ->assertDontSee(route('public.listings.show', $listing));

        $this->get(route('public.listings.show', $listing))->assertNotFound();

        $this->actingAs($renter)->delete(route('favorites.destroy', $listing))->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['user_id' => $renter->id, 'listing_id' => $listing->id]);
    }

    public function test_public_cards_and_detail_render_renter_favorite_state_and_guest_login_affordance(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $admin = $this->userWithRoles('ADMIN');
        $landlordOnly = $this->userWithRoles('LANDLORD');
        $listing = $this->createListing('Phòng cho thuê');

        $this->get(route('public.listings.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.login_to_save'))
            ->assertDontSee(route('favorites.store', $listing));

        $this->actingAs($renter)
            ->get(route('public.listings.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.save'))
            ->assertSee('aria-pressed="false"', false);

        foreach ([$admin, $landlordOnly] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('public.listings.index'))
                ->assertOk()
                ->assertDontSee(__('ui.favorites.save'))
                ->assertDontSee(__('ui.favorites.login_to_save'));
        }

        $this->actingAs($renter)
            ->get(route('public.listings.show', $listing))
            ->assertOk()
            ->assertSee(__('ui.favorites.save'))
            ->assertSee('aria-pressed="false"', false);

        $this->actingAs($renter)->post(route('favorites.store', $listing));

        $this->actingAs($renter)
            ->get(route('public.listings.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.remove'))
            ->assertSee('aria-pressed="true"', false);

        $this->get(route('public.listings.show', $listing))
            ->assertOk()
            ->assertSee(__('ui.favorites.remove'))
            ->assertSee('aria-pressed="true"', false);

        $this->delete(route('favorites.destroy', $listing));

        $this->get(route('public.listings.index'))
            ->assertOk()
            ->assertSee(__('ui.favorites.save'))
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_wishlist_cards_eager_load_only_required_public_card_relationships(): void
    {
        $renter = $this->userWithRoles('RENTER');
        $listing = $this->createListing('Tin công khai trong Wishlist');
        $renter->favorites()->attach($listing->id, ['created_at' => now()]);

        $response = $this->actingAs($renter)->get(route('wishlist.index'))->assertOk();
        $favorite = $response->viewData('favorites')->first();

        $this->assertTrue($favorite->relationLoaded('category'));
        $this->assertTrue($favorite->relationLoaded('coverImage'));
        $this->assertTrue($favorite->relationLoaded('ward'));
        $this->assertTrue($favorite->ward->relationLoaded('district'));
        $this->assertTrue($favorite->ward->district->relationLoaded('province'));
        $this->assertFalse($favorite->relationLoaded('images'));
        $this->assertFalse($favorite->relationLoaded('currentModeration'));
        $this->assertFalse($favorite->relationLoaded('moderations'));
        $this->assertFalse($favorite->relationLoaded('fees'));
        $this->assertFalse($favorite->relationLoaded('amenities'));
        $response->assertSee('Tin công khai trong Wishlist')
            ->assertSee('Phòng trọ')
            ->assertSee('Hà Nội · Cầu Giấy · Dịch Vọng')
            ->assertSee('2.500.000')
            ->assertSee('22,00')
            ->assertSee('cover.jpg');
    }

    public function test_public_search_loads_favorite_state_in_one_bounded_query(): void
    {
        $renter = $this->userWithRoles('RENTER');

        foreach (range(1, 11) as $number) {
            $listing = $this->createListing('Phòng '.$number);

            if ($number % 2 === 0) {
                $renter->favorites()->attach($listing->id, ['created_at' => now()]);
            }
        }

        $favoriteQueries = [];
        DB::listen(function (QueryExecuted $query) use (&$favoriteQueries): void {
            if (str_contains(strtolower($query->sql), 'favorites')) {
                $favoriteQueries[] = $query->sql;
            }
        });

        $response = $this->actingAs($renter)->get(route('public.listings.index'))->assertOk();

        $this->assertCount(1, $favoriteQueries);
        $this->assertCount(10, $response->viewData('listings')->items());
    }

    private function createListing(string $title, array $overrides = []): Listing
    {
        $moderationStatus = $overrides['moderation_status'] ?? 'APPROVED';
        $rejectionReason = $overrides['rejection_reason'] ?? null;
        unset($overrides['moderation_status'], $overrides['rejection_reason']);

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
            'rejection_reason' => $moderationStatus === 'REJECTED' ? ($rejectionReason ?? 'Tin cần chỉnh sửa.') : null,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => $moderationStatus === 'PENDING' ? null : now()->subDay(),
        ]);
        $listing->forceFill(['current_moderation_id' => $moderation->id])->save();
        $listing->images()->create([
            'image_url' => 'listings/'.$listing->id.'/cover.jpg',
            'is_cover' => true,
            'display_order' => 0,
            'created_at' => now(),
        ]);

        return $listing->fresh();
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();

        foreach (Role::query()->whereIn('code', $roles)->get() as $role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user;
    }
}
