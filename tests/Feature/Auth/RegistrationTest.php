<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_registration_page_loads(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Tạo tài khoản người tìm trọ');
    }

    public function test_authenticated_user_cannot_use_guest_registration_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/register')->assertRedirect('/');
        $this->actingAs($user)->post('/register', $this->validData())->assertRedirect('/');
    }

    public function test_email_only_registration_creates_complete_renter_account_and_authenticates(): void
    {
        $this->seed(RoleSeeder::class);
        $this->withSession(['session_marker' => true]);
        $originalSessionId = $this->app['session']->getId();

        $response = $this->post('/register', $this->validData(['phone' => null]));

        $response->assertRedirect('/');
        $user = User::where('email', 'renter@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, $response->getSession()->getId());
        $this->assertNull($user->phone);
        $this->assertSame('ACTIVE', $user->account_status);
        $this->assertSame(0, $user->failed_login_count);
        $this->assertNull($user->login_blocked_until);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->last_login_at);
        $this->assertTrue(Hash::check('password123', $user->password_hash));
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'full_name' => 'Test Renter',
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $user->id,
            'role_id' => Role::where('code', 'RENTER')->value('id'),
            'assigned_by' => null,
        ]);
    }

    public function test_phone_only_registration_trims_and_stores_phone(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', $this->validData([
            'email' => null,
            'phone' => '  0901234567  ',
        ]))->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => null,
            'phone' => '0901234567',
        ]);
    }

    public function test_phone_with_leading_country_code_is_accepted(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', $this->validData([
            'email' => null,
            'phone' => '+84912345678',
        ]))->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'email' => null,
            'phone' => '+84912345678',
        ]);
    }

    public function test_email_shaped_phone_is_rejected(): void
    {
        $this->post('/register', $this->validData([
            'email' => null,
            'phone' => 'abc@example.com',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_alphabetic_phone_is_rejected(): void
    {
        $this->post('/register', $this->validData([
            'email' => null,
            'phone' => 'phoneNumber',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_phone_with_internal_spaces_or_punctuation_is_rejected(): void
    {
        foreach (['0912 345 678', '0912-345-678', '(0912)345678'] as $phone) {
            $this->post('/register', $this->validData([
                'email' => null,
                'phone' => $phone,
            ]))->assertSessionHasErrors('phone');
        }
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'renter@example.com']);

        $this->from('/register')->post('/register', $this->validData())
            ->assertRedirect('/register')
            ->assertSessionHasErrors('email');
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => '0901234567']);

        $this->from('/register')->post('/register', $this->validData(['phone' => '0901234567']))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('phone');
    }

    public function test_at_least_one_identifier_is_required(): void
    {
        $this->post('/register', $this->validData(['email' => null, 'phone' => null]))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_password_must_be_at_least_eight_characters(): void
    {
        $this->post('/register', $this->validData([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');
    }

    public function test_public_role_input_cannot_escalate_privileges(): void
    {
        $this->seed(RoleSeeder::class);

        $this->post('/register', $this->validData([
            'role' => 'SUPER_ADMIN',
            'roles' => ['ADMIN', 'LANDLORD'],
            'account_status' => 'LOCKED',
        ]))->assertRedirect('/');

        $user = User::where('email', 'renter@example.com')->firstOrFail();

        $this->assertSame(['RENTER'], $user->roles()->pluck('code')->all());
        $this->assertSame('ACTIVE', $user->account_status);
    }

    public function test_missing_renter_role_rolls_back_all_registration_records(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->post('/register', $this->validData());
            $this->fail('Registration should fail when the RENTER role is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The required RENTER role is not configured.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('user_roles', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Test Renter',
            'email' => 'renter@example.com',
            'phone' => '0901234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }
}
