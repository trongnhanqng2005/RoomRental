<?php

namespace Tests\Feature\Listing;

use App\Models\Amenity;
use App\Models\District;
use App\Models\FeeType;
use App\Models\FeeUnit;
use App\Models\Listing;
use App\Models\Province;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use App\Models\Ward;
use App\Services\ListingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ListingManagementTest extends TestCase
{
    use RefreshDatabase;

    private RoomCategory $category;

    private Amenity $amenity;

    private Province $province;

    private District $district;

    private Ward $ward;

    private FeeType $feeType;

    private FeeUnit $feeUnit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);
        $this->category = RoomCategory::query()->create(['name' => 'Phòng trọ', 'is_active' => true]);
        $this->amenity = Amenity::query()->create(['name' => 'Wi-Fi', 'is_active' => true]);
        $this->province = Province::query()->create(['code' => 'P01', 'name' => 'Hà Nội', 'is_active' => true]);
        $this->district = District::query()->create(['province_id' => $this->province->id, 'code' => 'D01', 'name' => 'Cầu Giấy', 'is_active' => true]);
        $this->ward = Ward::query()->create(['district_id' => $this->district->id, 'code' => 'W01', 'name' => 'Dịch Vọng', 'is_active' => true]);
        $this->feeType = FeeType::query()->create(['code' => 'ELECTRICITY', 'name' => 'Điện', 'is_active' => true]);
        $this->feeUnit = FeeUnit::query()->create(['code' => 'PER_KWH', 'name' => 'Theo kWh', 'is_active' => true]);
    }

    public function test_guest_cannot_access_landlord_listing_management(): void
    {
        $this->get(route('landlord.listings.index'))->assertRedirect('/login');
        $this->get(route('landlord.listings.create'))->assertRedirect('/login');
    }

    public function test_renter_can_open_first_listing_form_but_non_landlord_cannot_open_index(): void
    {
        $renter = $this->userWithRole('RENTER');

        $this->actingAs($renter)->get(route('landlord.listings.create'))->assertOk();
        $this->actingAs($renter)->get(route('landlord.listings.index'))->assertForbidden();
    }

    public function test_first_listing_requires_phone_and_contact_address(): void
    {
        $user = User::factory()->create(['email' => 'incomplete@example.com', 'phone' => null]);
        $user->profile()->create(['full_name' => 'Incomplete Renter', 'contact_address' => null]);
        $user->roles()->attach(Role::query()->where('code', 'RENTER')->value('id'), ['assigned_at' => now()]);

        $this->actingAs($user)
            ->from(route('landlord.listings.create'))
            ->post(route('landlord.listings.store'), $this->validData())
            ->assertRedirect(route('landlord.listings.create'))
            ->assertSessionHasErrors(['phone', 'contact_address']);

        $this->assertDatabaseCount('listings', 0);
        $this->assertFalse($user->fresh()->hasRole('LANDLORD'));
    }

    public function test_valid_first_listing_creates_details_moderation_and_landlord_capability(): void
    {
        $user = $this->userWithRole('RENTER');

        $response = $this->actingAs($user)->post(route('landlord.listings.store'), $this->validData());

        $response->assertRedirect(route('landlord.listings.index'));
        $listing = Listing::query()->firstOrFail();
        $moderation = $listing->currentModeration;

        $this->assertNotNull($moderation);
        $this->assertSame('PENDING', $moderation->status);
        $this->assertSame(1, $moderation->version_no);
        $this->assertSame($moderation->id, $listing->current_moderation_id);
        $this->assertSame('AVAILABLE', $listing->occupancy_status);
        $this->assertSame('VISIBLE', $listing->visibility_status);
        $this->assertSame(['LANDLORD', 'RENTER'], $user->fresh()->roles()->orderBy('code')->pluck('code')->all());
        $this->assertDatabaseCount('listing_images', 3);
        $this->assertDatabaseCount('listing_fees', 1);
        $this->assertDatabaseCount('listing_amenities', 1);
        $this->assertSame(1, $listing->images()->where('is_cover', true)->count());

        $this->actingAs($user->fresh())
            ->get(route('landlord.listings.index'))
            ->assertOk()
            ->assertSee('Phòng gần trung tâm');

        $this->actingAs($user->fresh())
            ->get(route('landlord.listings.edit', $listing))
            ->assertOk()
            ->assertSee('Chỉnh sửa tin đăng');
    }

    public function test_landlord_role_activation_is_idempotent_for_multiple_listings(): void
    {
        $user = $this->userWithRole('RENTER');
        $service = app(ListingService::class);

        $service->create($user, $this->validData());
        $service->create($user, $this->validData(['title' => 'Phòng thứ hai']));

        $this->assertSame(1, $user->fresh()->roles()->where('code', 'LANDLORD')->count());
        $this->assertDatabaseCount('listings', 2);
    }

    public function test_listing_validation_rejects_invalid_data_and_image_boundaries(): void
    {
        $user = $this->userWithRole('RENTER');
        $data = $this->validData([
            'title' => '',
            'monthly_rent' => -1,
            'ward_id' => $this->ward->id + 100,
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
            'cover_selection' => 'new:4',
        ]);

        $this->actingAs($user)
            ->from(route('landlord.listings.create'))
            ->post(route('landlord.listings.store'), $data)
            ->assertRedirect(route('landlord.listings.create'))
            ->assertSessionHasErrors(['title', 'monthly_rent', 'ward_id', 'images', 'cover_selection']);

        $this->actingAs($user)
            ->post(route('landlord.listings.store'), $this->validData([
                'images' => array_merge(
                    $this->fakeImages(8),
                    [UploadedFile::fake()->image('nine.jpg')],
                ),
            ]))
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_listing_update_is_owner_only_and_a_critical_edit_preserves_history(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $otherLandlord = $this->userWithRole('LANDLORD', 'other@example.com');
        $listing = $this->createListing($owner);
        $otherListing = $this->createListing($otherLandlord, 'Other listing');

        $this->actingAs($owner)
            ->put(route('landlord.listings.update', $otherListing), $this->updateData($otherListing, ['title' => 'IDOR']))
            ->assertForbidden();

        $this->actingAs($owner)
            ->put(route('landlord.listings.update', $listing), $this->updateData($listing, ['title' => 'Căn phòng mới']))
            ->assertRedirect(route('landlord.listings.index'));

        $listing->refresh();
        $this->assertSame('Căn phòng mới', $listing->title);
        $this->assertSame(2, $listing->moderations()->count());
        $this->assertSame(2, $listing->currentModeration->version_no);
        $this->assertSame('PENDING', $listing->currentModeration->status);
        $this->assertDatabaseHas('listing_moderations', ['listing_id' => $listing->id, 'version_no' => 1, 'status' => 'PENDING']);
    }

    public function test_noop_update_does_not_create_a_new_moderation_version(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($owner);

        $this->actingAs($owner)
            ->put(route('landlord.listings.update', $listing), $this->updateData($listing))
            ->assertRedirect(route('landlord.listings.index'));

        $this->assertDatabaseCount('listing_moderations', 1);
    }

    public function test_occupancy_and_visibility_change_independently_without_new_moderation(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($owner);

        $this->actingAs($owner)
            ->patch(route('landlord.listings.occupancy', $listing), ['occupancy_status' => 'RENTED'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->patch(route('landlord.listings.visibility', $listing), ['visibility_status' => 'HIDDEN'])
            ->assertRedirect();

        $listing->refresh();
        $this->assertSame('RENTED', $listing->occupancy_status);
        $this->assertSame('HIDDEN', $listing->visibility_status);
        $this->assertSame(1, $listing->moderations()->count());
    }

    public function test_landlord_cannot_unsuspend_a_listing(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($owner);
        $listing->visibility_status = 'SUSPENDED';
        $listing->save();

        $this->actingAs($owner)
            ->patch(route('landlord.listings.visibility', $listing), ['visibility_status' => 'VISIBLE'])
            ->assertForbidden();

        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'visibility_status' => 'SUSPENDED']);
    }

    public function test_update_replaces_files_after_commit_and_keeps_one_cover(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($owner);
        $listing->load('images');
        $removedPath = $listing->images->last()->image_url;
        $retainedIds = $listing->images->take(2)->pluck('id')->all();
        $newImage = UploadedFile::fake()->image('replacement.jpg');

        $data = $this->updateData($listing, [
            'existing_images' => $retainedIds,
            'new_images' => [$newImage],
            'cover_selection' => 'new:0',
        ]);

        $this->actingAs($owner)
            ->put(route('landlord.listings.update', $listing), $data)
            ->assertRedirect(route('landlord.listings.index'));

        $listing->refresh();
        $this->assertSame(3, $listing->images()->count());
        $this->assertSame(1, $listing->images()->where('is_cover', true)->count());
        Storage::disk('public')->assertMissing($removedPath);
    }

    public function test_creation_rolls_back_database_rows_and_new_files_when_role_activation_fails(): void
    {
        $user = $this->userWithRole('RENTER');
        Role::query()->where('code', 'LANDLORD')->delete();

        try {
            app(ListingService::class)->create($user, $this->validData());
            $this->fail('Listing creation should fail when LANDLORD role configuration is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The required LANDLORD role is not configured.', $exception->getMessage());
        }

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('listing_images', 0);
        $this->assertDatabaseCount('listing_moderations', 0);
        Storage::disk('public')->assertDirectoryEmpty('listings');
    }

    public function test_update_rollback_keeps_the_previous_current_moderation_pointer(): void
    {
        $owner = $this->userWithRole('LANDLORD');
        $listing = $this->createListing($owner);
        $listing->load('images');
        $previousModerationId = $listing->current_moderation_id;
        $oldPaths = $listing->images->pluck('image_url')->all();

        Listing::saving(function (Listing $model): void {
            if ($model->exists && $model->isDirty('current_moderation_id')) {
                throw new RuntimeException('Forced moderation pointer failure.');
            }
        });

        try {
            app(ListingService::class)->update($listing, $this->updateData($listing, [
                'title' => 'Thay đổi cần kiểm tra rollback',
                'new_images' => [UploadedFile::fake()->image('rollback.jpg')],
                'cover_selection' => 'new:0',
            ]));
            $this->fail('The forced moderation pointer failure should roll back the update.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced moderation pointer failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.saving: '.Listing::class);
        }

        $listing->refresh();
        $this->assertSame($previousModerationId, $listing->current_moderation_id);
        $this->assertSame(1, $listing->moderations()->count());
        $this->assertSame('Phòng gần trung tâm', $listing->title);
        $this->assertSame($oldPaths, $listing->images()->pluck('image_url')->all());
        Storage::disk('public')->assertCount('listings/'.$listing->id, 3);
    }

    private function createListing(User $user, string $title = 'Phòng gần trung tâm'): Listing
    {
        return app(ListingService::class)->create($user, $this->validData(['title' => $title]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Phòng gần trung tâm',
            'description' => 'Phòng sáng, có cửa sổ và khu vực để xe.',
            'monthly_rent' => '3500000.00',
            'deposit_amount' => '3500000.00',
            'area_m2' => '24.50',
            'max_occupants' => 2,
            'bedroom_count' => 1,
            'bathroom_count' => 1,
            'gender_requirement' => 'ANY',
            'province_id' => $this->province->id,
            'district_id' => $this->district->id,
            'ward_id' => $this->ward->id,
            'street_address' => '12 Nguyễn Trãi',
            'latitude' => '21.0285110',
            'longitude' => '105.8048170',
            'amenity_ids' => [$this->amenity->id],
            'fees' => [[
                'fee_type_id' => $this->feeType->id,
                'fee_unit_id' => $this->feeUnit->id,
                'amount' => '3500.00',
                'note' => 'Theo công tơ',
            ]],
            'images' => $this->fakeImages(3),
            'cover_selection' => 'new:0',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function updateData(Listing $listing, array $overrides = []): array
    {
        $listing->load(['ward.district', 'images', 'amenities', 'fees']);

        return array_merge($this->validData([
            'category_id' => $listing->category_id,
            'title' => $listing->title,
            'description' => $listing->description,
            'monthly_rent' => $listing->monthly_rent,
            'deposit_amount' => $listing->deposit_amount,
            'area_m2' => $listing->area_m2,
            'max_occupants' => $listing->max_occupants,
            'bedroom_count' => $listing->bedroom_count,
            'bathroom_count' => $listing->bathroom_count,
            'gender_requirement' => $listing->gender_requirement,
            'province_id' => $listing->ward->district->province_id,
            'district_id' => $listing->ward->district_id,
            'ward_id' => $listing->ward_id,
            'street_address' => $listing->street_address,
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'amenity_ids' => $listing->amenities->pluck('id')->all(),
            'fees' => $listing->fees->map(fn ($fee) => [
                'fee_type_id' => $fee->fee_type_id,
                'fee_unit_id' => $fee->fee_unit_id,
                'amount' => $fee->amount,
                'note' => $fee->note,
            ])->all(),
            'existing_images' => $listing->images->pluck('id')->all(),
            'new_images' => [],
            'cover_selection' => 'existing:'.$listing->images->firstWhere('is_cover', true)->id,
        ]), $overrides);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function fakeImages(int $count): array
    {
        return collect(range(1, $count))->map(fn ($index) => UploadedFile::fake()->image("room-{$index}.jpg"))->all();
    }

    private function userWithRole(string $role, string $email = 'landlord@example.com'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'phone' => '09'.str_pad((string) (abs(crc32($email)) % 100000000), 8, '0', STR_PAD_LEFT),
        ]);
        $user->profile()->create([
            'full_name' => 'Test Landlord',
            'contact_address' => '12 Nguyen Trai, Ha Noi',
        ]);
        $user->roles()->attach(Role::query()->where('code', $role)->value('id'), ['assigned_at' => now()]);

        return $user;
    }
}
