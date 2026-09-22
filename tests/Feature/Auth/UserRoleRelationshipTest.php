<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_profile_relationships_work_in_both_directions(): void
    {
        $user = User::factory()->create();
        $profile = UserProfile::create([
            'user_id' => $user->id,
            'full_name' => 'Test Renter',
        ]);

        $this->assertTrue($user->profile->is($profile));
        $this->assertTrue($profile->user->is($user));
    }

    public function test_user_and_role_relationships_preserve_pivot_metadata(): void
    {
        $this->seed(RoleSeeder::class);

        $assigner = User::factory()->create();
        $user = User::factory()->create();
        $role = Role::where('code', 'RENTER')->firstOrFail();
        $assignedAt = now()->startOfSecond();

        $user->roles()->attach($role->id, [
            'assigned_by' => $assigner->id,
            'assigned_at' => $assignedAt,
        ]);

        $assignedRole = $user->roles()->firstOrFail();
        $roleUser = $role->users()->firstOrFail();

        $this->assertTrue($assignedRole->is($role));
        $this->assertTrue($roleUser->is($user));
        $this->assertSame($assigner->id, $assignedRole->pivot->assigned_by);
        $this->assertSame($assignedAt->toDateTimeString(), $assignedRole->pivot->assigned_at);
    }

    public function test_role_helpers_support_single_multiple_and_array_arguments(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $roles = Role::whereIn('code', ['RENTER', 'LANDLORD'])->get();

        foreach ($roles as $role) {
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        $this->assertTrue($user->hasRole('RENTER'));
        $this->assertTrue($user->hasAnyRole('ADMIN', 'LANDLORD'));
        $this->assertTrue($user->hasAnyRole(['ADMIN', 'RENTER']));
        $this->assertFalse($user->hasRole('ADMIN'));
        $this->assertFalse($user->hasAnyRole([]));
    }
}
