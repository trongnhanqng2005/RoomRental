<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Route::middleware(['web', 'auth'])
            ->get('/test/protected', fn () => response('protected'));
        Route::middleware(['web', 'auth', 'role:RENTER'])
            ->get('/test/renter', fn () => response('renter'));
        Route::middleware(['web', 'auth', 'role:ADMIN,LANDLORD'])
            ->get('/test/multi-role', fn () => response('multi-role'));
        Route::middleware(['web', 'auth', 'role:ADMIN,SUPER_ADMIN'])
            ->get('/test/admin', fn () => response('admin'));
    }

    public function test_guest_is_redirected_to_login_before_role_authorization(): void
    {
        $this->get('/test/renter')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_general_protected_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/test/protected')->assertOk();
    }

    public function test_user_with_correct_role_can_access_role_route(): void
    {
        $user = $this->userWithRoles('RENTER');

        $this->actingAs($user)->get('/test/renter')->assertOk();
    }

    public function test_user_with_wrong_role_receives_forbidden_response(): void
    {
        $user = $this->userWithRoles('LANDLORD');

        $this->actingAs($user)->get('/test/renter')->assertForbidden();
    }

    public function test_multi_role_user_succeeds_when_any_allowed_role_matches(): void
    {
        $user = $this->userWithRoles('RENTER', 'LANDLORD');

        $this->actingAs($user)->get('/test/multi-role')->assertOk();
    }

    public function test_admin_route_explicitly_accepts_admin_and_super_admin(): void
    {
        $admin = $this->userWithRoles('ADMIN');
        $superAdmin = $this->userWithRoles('SUPER_ADMIN');

        $this->actingAs($admin)->get('/test/admin')->assertOk();
        $this->actingAs($superAdmin)->get('/test/admin')->assertOk();
    }

    public function test_renter_does_not_gain_admin_access(): void
    {
        $renter = $this->userWithRoles('RENTER');

        $this->actingAs($renter)->get('/test/admin')->assertForbidden();
    }

    private function userWithRoles(string ...$codes): User
    {
        $user = User::factory()->create();

        foreach (Role::whereIn('code', $codes)->get() as $role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user;
    }
}
