<?php

namespace Tests\Feature\Profile;

use App\Models\Role;
use App\Models\User;
use App\Services\ProfileService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_update_a_profile(): void
    {
        $this->patch('/profile', $this->validData())
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_update_allowed_account_and_profile_fields(): void
    {
        $user = $this->userWithProfile();

        $response = $this->actingAs($user)
            ->from('/')
            ->patch('/profile', $this->validData());

        $response->assertRedirect('/')->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
            'phone' => '+84912345678',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Updated Renter',
            'contact_address' => '12 Nguyen Trai, Ha Noi',
            'zalo_number' => '0912345678',
        ]);
    }

    public function test_full_name_is_required_and_does_not_partially_update_account_data(): void
    {
        $user = $this->userWithProfile(['email' => 'original@example.com']);

        $this->actingAs($user)
            ->from('/')
            ->patch('/profile', $this->validData([
                'full_name' => '   ',
                'email' => 'changed@example.com',
            ]))
            ->assertRedirect('/')
            ->assertSessionHasErrors('full_name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'original@example.com']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'full_name' => 'Original Renter']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_phone_values_are_rejected(): void
    {
        $user = $this->userWithProfile();

        foreach (['0912 345 678', '0912-345-678', '(0912)345678', 'phoneNumber', '++84912345678'] as $phone) {
            $this->actingAs($user)
                ->patch('/profile', $this->validData(['phone' => $phone]))
                ->assertSessionHasErrors('phone');
        }
    }

    public function test_account_and_profile_fields_respect_schema_lengths(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'full_name' => str_repeat('a', 151),
                'email' => str_repeat('a', 246).'@example.com',
                'phone' => str_repeat('1', 21),
            ]))
            ->assertSessionHasErrors(['full_name', 'email', 'phone']);
    }

    public function test_email_must_be_unique_except_for_the_authenticated_user(): void
    {
        $user = $this->userWithProfile(['email' => 'owner@example.com']);
        $this->userWithProfile(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['email' => 'owner@example.com']))
            ->assertSessionDoesntHaveErrors('email');
    }

    public function test_phone_must_be_unique_except_for_the_authenticated_user(): void
    {
        $user = $this->userWithProfile(['phone' => '0901000000']);
        $this->userWithProfile(['phone' => '0902000000']);

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['phone' => '0902000000']))
            ->assertSessionHasErrors('phone');

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['phone' => '0901000000']))
            ->assertSessionDoesntHaveErrors('phone');
    }

    public function test_database_email_conflict_rolls_back_the_profile_update(): void
    {
        $user = $this->userWithProfile(['email' => 'owner@example.com']);
        $this->userWithProfile(['email' => 'taken@example.com']);

        try {
            app(ProfileService::class)->update($user, [
                'email' => 'taken@example.com',
                'full_name' => 'Changed Renter',
            ]);
            $this->fail('A database email conflict should become a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'owner@example.com']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'full_name' => 'Original Renter']);
    }

    public function test_at_least_one_email_or_phone_must_remain_present(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData(['email' => ' ', 'phone' => ' ']))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_stale_identifier_update_cannot_clear_the_last_identifier(): void
    {
        $user = $this->userWithProfile([
            'email' => 'owner@example.com',
            'phone' => '0901000000',
        ]);
        $staleUser = $user->fresh();
        $user->phone = null;
        $user->save();

        try {
            app(ProfileService::class)->update($staleUser, ['email' => null]);
            $this->fail('Updating with stale account data should preserve the identifier requirement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'owner@example.com',
            'phone' => null,
        ]);
    }

    public function test_values_are_trimmed_and_blank_optional_values_become_null(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'full_name' => '  Updated Renter  ',
                'email' => '  updated@example.com  ',
                'phone' => '  +84912345678  ',
                'contact_address' => '   ',
                'zalo_number' => '   ',
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
            'phone' => '+84912345678',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Updated Renter',
            'contact_address' => null,
            'zalo_number' => null,
        ]);
    }

    public function test_patch_preserves_omitted_fields(): void
    {
        $user = $this->userWithProfile([
            'email' => 'owner@example.com',
            'phone' => '0901000000',
        ]);

        $this->actingAs($user)
            ->patch('/profile', ['contact_address' => 'Updated address'])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'owner@example.com',
            'phone' => '0901000000',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Original Renter',
            'contact_address' => 'Updated address',
            'zalo_number' => '0901234567',
        ]);
    }

    public function test_optional_profile_fields_respect_schema_lengths(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'contact_address' => str_repeat('a', 501),
                'zalo_number' => str_repeat('1', 21),
            ]))
            ->assertSessionHasErrors(['contact_address', 'zalo_number']);
    }

    public function test_another_users_id_cannot_redirect_the_update(): void
    {
        $user = $this->userWithProfile(['email' => 'owner@example.com']);
        $otherUser = $this->userWithProfile(['email' => 'other@example.com']);

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'email' => 'updated-owner@example.com',
                'user_id' => $otherUser->id,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'updated-owner@example.com']);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'email' => 'other@example.com']);
    }

    public function test_profile_payload_cannot_change_roles_or_grant_landlord(): void
    {
        $user = $this->userWithProfile();

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'role' => 'SUPER_ADMIN',
                'roles' => ['ADMIN', 'LANDLORD'],
                'role_id' => Role::where('code', 'LANDLORD')->value('id'),
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(['RENTER'], $user->fresh()->roles()->pluck('code')->all());
    }

    public function test_profile_payload_cannot_change_protected_account_or_avatar_fields(): void
    {
        $user = $this->userWithProfile();
        $user->password_hash = 'original-password-hash';
        $user->failed_login_count = 3;
        $user->login_blocked_until = now()->addHour();
        $user->must_change_password = true;
        $user->last_login_at = now()->subHour();
        $user->save();
        $user->profile->avatar_url = 'avatars/original.jpg';
        $user->profile->save();

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'account_status' => 'LOCKED',
                'password_hash' => 'injected-password-hash',
                'failed_login_count' => 0,
                'login_blocked_until' => null,
                'must_change_password' => false,
                'last_login_at' => null,
                'avatar_url' => 'https://attacker.example/avatar.jpg',
            ]))
            ->assertSessionDoesntHaveErrors();

        $freshUser = $user->fresh();
        $freshProfile = $freshUser->profile;

        $this->assertSame('ACTIVE', $freshUser->account_status);
        $this->assertSame('original-password-hash', $freshUser->password_hash);
        $this->assertSame(3, $freshUser->failed_login_count);
        $this->assertNotNull($freshUser->login_blocked_until);
        $this->assertTrue($freshUser->must_change_password);
        $this->assertNotNull($freshUser->last_login_at);
        $this->assertSame('avatars/original.jpg', $freshProfile->avatar_url);
    }

    public function test_missing_profile_fails_without_changing_the_user(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->patch('/profile', $this->validData(['email' => 'changed@example.com']));
            $this->fail('Profile update should fail when the profile record is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The user profile is missing.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'original@example.com']);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
    }

    public function test_valid_identifier_changes_preserve_the_current_session_and_replace_login_identifiers(): void
    {
        $user = $this->userWithProfile([
            'email' => 'old@example.com',
            'phone' => '0901000000',
        ]);

        $this->actingAs($user)
            ->patch('/profile', $this->validData([
                'email' => 'new@example.com',
                'phone' => '0902000000',
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertAuthenticatedAs($user->fresh());

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', ['identifier' => 'old@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $this->post('/login', ['identifier' => 'new@example.com', 'password' => 'password'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user->fresh());
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Updated Renter',
            'email' => 'updated@example.com',
            'phone' => '+84912345678',
            'contact_address' => '12 Nguyen Trai, Ha Noi',
            'zalo_number' => '0912345678',
        ], $overrides);
    }
}
