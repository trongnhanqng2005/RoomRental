<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporaryPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    public function test_temporary_password_login_is_restricted_until_password_change_then_restores_access(): void
    {
        $user = $this->userWithTemporaryPassword();

        $this->post('/login', ['identifier' => $user->email, 'password' => 'temporary-password'])
            ->assertRedirect(route('password.change.required'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('profile.show'))->assertRedirect(route('password.change.required'));
        $this->get(route('password.change.required'))->assertOk()->assertSee(__('ui.password_change.heading'));
        $this->get(route('admin.users.index'))->assertRedirect(route('password.change.required'));
        $this->get('/logout')->assertMethodNotAllowed();

        $this->put(route('password.change.update'), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect('/');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password_hash));
        $this->get(route('profile.show'))->assertOk();
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_temporary_password_change_requires_matching_confirmation_and_logout_remains_available(): void
    {
        $user = $this->userWithTemporaryPassword();
        $this->actingAs($user)
            ->put(route('password.change.update'), [
                'password' => 'new-secure-password',
                'password_confirmation' => 'does-not-match',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_locked_account_cannot_use_required_password_change_as_an_authentication_bypass(): void
    {
        $lockedUser = $this->userWithTemporaryPassword();
        $lockedUser->forceFill(['account_status' => 'LOCKED'])->save();

        $this->from('/login')->post('/login', ['identifier' => $lockedUser->email, 'password' => 'temporary-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $this->actingAs($lockedUser)
            ->get(route('password.change.required'))
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_existing_session_is_rejected_after_an_admin_resets_the_users_password(): void
    {
        $target = $this->userWithTemporaryPassword(false);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($target)->get(route('profile.show'))->assertOk();
        $oldFingerprint = $this->app['session']->get('password_hash_web');
        $this->assertNotNull($oldFingerprint);

        $this->withSession(['password_hash_web' => $admin->password_hash])
            ->actingAs($admin)
            ->post(route('admin.users.password-reset', $target))
            ->assertRedirect();
        $this->assertTrue($target->fresh()->must_change_password);

        $this->withSession(['password_hash_web' => $oldFingerprint])
            ->actingAs($target->fresh())
            ->get(route('profile.show'))
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    private function userWithTemporaryPassword(bool $mustChange = true): User
    {
        $user = User::factory()->create([
            'email' => 'temporary@example.test',
            'password_hash' => Hash::make('temporary-password'),
            'must_change_password' => $mustChange,
        ]);
        $user->profile()->create(['full_name' => 'Người dùng mật khẩu tạm']);
        $user->roles()->attach(Role::query()->where('code', 'RENTER')->value('id'), ['assigned_at' => now()]);

        return $user->fresh();
    }

    /** @param array<int, string> $roles */
    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create();
        $user->profile()->create(['full_name' => 'Quản trị viên']);

        foreach ($roles as $role) {
            $user->roles()->attach(Role::query()->where('code', $role)->value('id'), ['assigned_at' => now()]);
        }

        return $user->fresh();
    }
}
