<?php

namespace Tests\Feature\Admin;

use App\Models\Amenity;
use App\Models\Role;
use App\Models\RoomCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RoomCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->seed(RoomCategorySeeder::class);
    }

    public function test_non_admin_cannot_access_site_management(): void
    {
        $this->get(route('admin.site-management.index'))->assertRedirect('/login');

        foreach ([['RENTER'], ['LANDLORD'], ['RENTER', 'LANDLORD']] as $roles) {
            $user = $this->userWithRoles($roles);
            $this->actingAs($user)->get(route('admin.site-management.index'))->assertForbidden();
        }
    }

    public function test_admin_can_access_site_management_tabs(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);

        // Default tab is users
        $response = $this->actingAs($admin)->get(route('admin.site-management.index'));
        $response->assertOk();
        $response->assertViewHas('activeTab', 'users');
        $response->assertSee('Quản lý người dùng');

        // Tab: categories
        $categoryResponse = $this->actingAs($admin)->get(route('admin.site-management.index', ['tab' => 'categories']));
        $categoryResponse->assertOk();
        $categoryResponse->assertViewHas('activeTab', 'categories');
        $categoryResponse->assertSee('Danh mục phòng');

        // Tab: amenities
        Amenity::create(['name' => 'Wifi miễn phí', 'is_active' => true]);
        $amenityResponse = $this->actingAs($admin)->get(route('admin.site-management.index', ['tab' => 'amenities']));
        $amenityResponse->assertOk();
        $amenityResponse->assertViewHas('activeTab', 'amenities');
        $amenityResponse->assertSee('Wifi miễn phí');

        // Invalid tab falls back to users
        $fallbackResponse = $this->actingAs($admin)->get(route('admin.site-management.index', ['tab' => 'invalid_tab']));
        $fallbackResponse->assertOk();
        $fallbackResponse->assertViewHas('activeTab', 'users');
    }

    /** @param array<int, string> $roles @param array<string, mixed> $attributes */
    private function userWithRoles(array $roles, string $name = 'Tài khoản kiểm thử', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->profile()->create(['full_name' => $name]);

        foreach ($roles as $role) {
            $user->roles()->attach(Role::query()->where('code', $role)->value('id'), [
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);
        }

        return $user->fresh(['profile', 'roles']);
    }
}
