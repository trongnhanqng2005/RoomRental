<?php

namespace Tests\Feature\Profile;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProfileViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_view_a_profile(): void
    {
        $this->get('/profile')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_their_own_profile_and_roles(): void
    {
        $user = $this->userWithProfile([
            'email' => 'owner@example.com',
            'phone' => '0901000000',
        ]);
        $user->profile->avatar_url = 'avatars/owner.jpg';
        $user->profile->save();
        $user->roles()->attach(Role::where('code', 'LANDLORD')->value('id'), ['assigned_at' => now()]);

        $otherUser = $this->userWithProfile([
            'email' => 'other@example.com',
            'phone' => '0902000000',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertViewIs('profile.show')
            ->assertSee('Original Renter')
            ->assertSee('owner@example.com')
            ->assertSee('0901000000')
            ->assertSee('Original address')
            ->assertSee('0901234567')
            ->assertSee('avatars/owner.jpg')
            ->assertSee('Người tìm trọ')
            ->assertSee('Chủ trọ')
            ->assertDontSee('other@example.com')
            ->assertDontSee('0902000000')
            ->assertDontSee('other@example.com');
    }

    public function test_profile_renders_success_feedback_and_old_input(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->withSession([
                'status' => 'Thông tin tài khoản đã được cập nhật.',
                '_old_input' => [
                    'full_name' => 'Updated Renter',
                    'email' => 'updated@example.com',
                    'phone' => '0909999999',
                    'contact_address' => 'Updated address',
                    'zalo_number' => '0988888888',
                ],
            ])
            ->get('/profile')
            ->assertOk()
            ->assertSee('Thông tin tài khoản đã được cập nhật.')
            ->assertSee('value="Updated Renter"', false)
            ->assertSee('value="updated@example.com"', false)
            ->assertSee('value="0909999999"', false)
            ->assertSee('Updated address')
            ->assertSee('value="0988888888"', false);
    }

    public function test_missing_profile_fails_without_repairing_the_record(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->get('/profile');
            $this->fail('Profile view should fail when the profile record is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The user profile is missing.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
    }

    public function test_profile_update_flow_still_redirects_to_profile_with_success_status(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'full_name' => 'Updated Renter',
                'email' => 'updated@example.com',
                'phone' => '0909999999',
                'contact_address' => 'Updated address',
                'zalo_number' => '0988888888',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'Thông tin tài khoản đã được cập nhật.');

        $this->actingAs($user)->get('/profile')->assertSee('Thông tin tài khoản đã được cập nhật.');
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     */
    private function userWithProfile(array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $user->profile()->create([
            'full_name' => 'Original Renter',
            'contact_address' => 'Original address',
            'zalo_number' => '0901234567',
        ]);
        $user->roles()->attach(Role::where('code', 'RENTER')->value('id'), ['assigned_at' => now()]);

        return $user;
    }
}
