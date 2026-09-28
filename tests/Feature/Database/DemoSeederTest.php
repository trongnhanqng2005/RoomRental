<?php

namespace Tests\Feature\Database;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\RoomCategory;
use App\Models\User;
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

    public function test_demo_seeder_creates_a_coherent_local_only_demo_and_generated_png_images(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        app(DemoSeeder::class)->run();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('listings', 12);
        $this->assertDatabaseCount('provinces', 2);
        $this->assertDatabaseCount('districts', 2);
        $this->assertDatabaseCount('wards', 4);
        $this->assertDatabaseCount('amenities', 6);
        $this->assertDatabaseCount('viewing_slots', 5);
        $this->assertDatabaseCount('appointments', 5);
        $this->assertDatabaseCount('reports', 4);
        $this->assertDatabaseCount('enforcement_actions', 2);

        $renter = User::query()->where('email', 'demo-renter@roomrental.test')->firstOrFail();
        $landlord = User::query()->where('email', 'demo-landlord@roomrental.test')->firstOrFail();
        $admin = User::query()->where('email', 'demo-admin@roomrental.test')->firstOrFail();
        $this->assertTrue(Hash::check(DemoSeeder::DEMO_PASSWORD, $renter->password_hash));
        $this->assertTrue($renter->hasRole('RENTER'));
        $this->assertTrue($landlord->hasRole('RENTER'));
        $this->assertTrue($landlord->hasRole('LANDLORD'));
        $this->assertTrue($admin->hasRole('ADMIN'));
        $this->assertFalse($renter->hasRole('SUPER_ADMIN'));
        $this->assertFalse($landlord->hasRole('SUPER_ADMIN'));
        $this->assertFalse($admin->hasRole('SUPER_ADMIN'));
        $this->assertDatabaseCount('user_roles', 4);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseHas('room_categories', ['name' => 'Danh mục ẩn (Demo)', 'is_active' => false]);
        $this->assertDatabaseHas('amenities', ['name' => 'Ban công', 'is_active' => false]);
        $this->assertDatabaseHas('appointments', ['status' => 'PENDING']);
        $this->assertDatabaseHas('appointments', ['status' => 'ACCEPTED']);
        $this->assertDatabaseHas('appointments', ['status' => 'COMPLETED']);
        $this->assertDatabaseHas('appointments', ['status' => 'CANCELLED', 'cancelled_by' => $renter->id]);
        $this->assertDatabaseHas('appointments', ['status' => 'AUTO_CANCELLED', 'cancelled_by' => null, 'cancellation_reason' => 'VIEWING_TIME_PASSED']);
        $this->assertDatabaseHas('reports', ['status' => 'PENDING']);
        $this->assertDatabaseHas('reports', ['status' => 'DISMISSED']);
        $this->assertSame(2, DB::table('reports')->where('status', 'RESOLVED')->count());

        $this->assertSame(2, DB::table('districts')->where('code', 'D01')->count());
        $this->assertSame(2, DB::table('wards')->where('code', 'W01')->count());
        $this->assertSame(0, DB::table('districts')->select('province_id', 'code')->groupBy('province_id', 'code')->havingRaw('COUNT(*) > 1')->count());
        $this->assertSame(0, DB::table('wards')->select('district_id', 'code')->groupBy('district_id', 'code')->havingRaw('COUNT(*) > 1')->count());

        foreach (Listing::query()->with('currentModeration')->get() as $listing) {
            $this->assertSame($listing->id, $listing->currentModeration->listing_id);
            $this->assertTrue($listing->ward()->exists());
            $this->assertSame(3, $listing->images()->count());
            $this->assertSame(1, $listing->images()->where('is_cover', true)->count());
        }

        $expired = Listing::query()->where('title', 'Phòng demo expired')->firstOrFail();
        $this->assertTrue($expired->isExpired());
        $this->assertSame('SUSPENDED', Listing::query()->where('title', 'Phòng demo suspended')->value('visibility_status'));
        $this->assertDatabaseHas('enforcement_actions', ['action_type' => 'SUSPEND_LISTING']);
        $this->assertDatabaseHas('enforcement_actions', ['action_type' => 'WARNING']);

        $firstImage = ListingImage::query()->firstOrFail();
        $this->assertStringStartsWith('demo-fixtures/listings/', $firstImage->image_url);
        Storage::disk('public')->assertExists($firstImage->image_url);
        $png = Storage::disk('public')->get($firstImage->image_url);
        $imageInfo = getimagesizefromstring($png);
        $this->assertNotFalse($imageInfo);
        $this->assertSame(IMAGETYPE_PNG, $imageInfo[2]);
        $this->assertSame([320, 240], [$imageInfo[0], $imageInfo[1]]);

        $this->actingAs($renter)
            ->get(route('landlord.listings.create'))
            ->assertOk()
            ->assertSee('Tỉnh demo 01 (fixture legacy V1)')
            ->assertSee('Phường demo 02-01-02');

        $publicListing = Listing::query()->where('title', 'Phòng demo available')->firstOrFail();
        $this->actingAs($renter)->get(route('public.listings.show', $publicListing))->assertOk();
        $hiddenListing = Listing::query()->where('title', 'Phòng demo hidden')->firstOrFail();
        $this->get(route('public.listings.show', $hiddenListing))->assertNotFound();

        try {
            app(DemoSeeder::class)->run();
            $this->fail('DemoSeeder must refuse a repeated run instead of duplicating or overwriting fixtures.');
        } catch (RuntimeException $exception) {
            $this->assertSame('DemoSeeder requires a fresh, disposable database. Recreate it before seeding demo data.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('listings', 12);

        $province = DB::table('provinces')->where('code', 'DEMO-P01')->first();
        $district = DB::table('districts')->where('province_id', $province->id)->where('code', 'D01')->first();
        $ward = DB::table('wards')->where('district_id', $district->id)->where('code', 'W01')->first();
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
                'street_address' => '12 Đường demo',
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

        $this->assertDatabaseHas('listings', [
            'title' => 'Phòng tạo từ fixture location',
            'ward_id' => $ward->id,
        ]);
        $this->assertDatabaseCount('listings', 13);
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
