<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Đăng nhập vào RoomRental');
    }

    public function test_active_user_can_login_by_email(): void
    {
        $user = User::factory()->create(['email' => 'renter@example.com']);
        $this->withSession(['session_marker' => true]);
        $originalSessionId = $this->app['session']->getId();

        $response = $this->post('/login', [
            'identifier' => '  renter@example.com  ',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, $response->getSession()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_active_user_can_login_by_phone(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '0901234567',
        ]);

        $this->post('/login', [
            'identifier' => '  0901234567  ',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_uses_intended_redirect(): void
    {
        $user = User::factory()->create();

        $this->withSession(['url.intended' => '/intended-destination'])
            ->post('/login', [
                'identifier' => $user->email,
                'password' => 'password',
            ])->assertRedirect('/intended-destination');
    }

    public function test_bad_password_nonexistent_identifier_and_locked_account_use_same_error(): void
    {
        $activeUser = User::factory()->create(['email' => 'active@example.com']);
        $lockedUser = User::factory()->locked()->create(['email' => 'locked@example.com']);

        $badPassword = $this->from('/login')->post('/login', [
            'identifier' => $activeUser->email,
            'password' => 'incorrect-password',
        ]);
        $nonexistent = $this->from('/login')->post('/login', [
            'identifier' => 'missing@example.com',
            'password' => 'password',
        ]);
        $locked = $this->from('/login')->post('/login', [
            'identifier' => $lockedUser->email,
            'password' => 'password',
        ]);

        $badPassword->assertRedirect('/login')->assertSessionHasErrors('identifier');
        $nonexistent->assertRedirect('/login')->assertSessionHasErrors('identifier');
        $locked->assertRedirect('/login')->assertSessionHasErrors('identifier');
        $this->assertGuest();

        $this->assertSame(
            $badPassword->getSession()->get('errors')->first('identifier'),
            $nonexistent->getSession()->get('errors')->first('identifier'),
        );
        $this->assertSame(
            $badPassword->getSession()->get('errors')->first('identifier'),
            $locked->getSession()->get('errors')->first('identifier'),
        );
    }

    public function test_login_is_limited_after_five_failed_attempts(): void
    {
        $identifier = 'limited@example.com';
        $key = $this->throttleKey($identifier);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'identifier' => $identifier,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('identifier');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        $response = $this->post('/login', [
            'identifier' => $identifier,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('identifier');

        $this->assertStringStartsWith(
            'Bạn đã đăng nhập sai quá nhiều lần.',
            $response->getSession()->get('errors')->first('identifier'),
        );
    }

    public function test_login_validation_messages_are_in_vietnamese(): void
    {
        $response = $this->post('/login', [
            'identifier' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'identifier' => 'Trường email hoặc số điện thoại là bắt buộc.',
            'password' => 'Trường mật khẩu là bắt buộc.',
        ]);
    }

    public function test_successful_login_clears_rate_limiter(): void
    {
        $user = User::factory()->create(['email' => 'renter@example.com']);
        $key = $this->throttleKey($user->email);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            RateLimiter::hit($key, 60);
        }

        $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_authenticated_user_can_logout_with_post(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            '_token' => 'known-csrf-token',
            'session_marker' => true,
        ]);
        $originalSessionId = $this->app['session']->getId();

        $response = $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($originalSessionId, $response->getSession()->getId());
        $this->assertNotSame('known-csrf-token', $response->getSession()->token());
        $this->assertFalse($response->getSession()->has('session_marker'));
    }

    public function test_logout_is_not_available_via_get(): void
    {
        $this->get('/logout')->assertMethodNotAllowed();
    }

    private function throttleKey(string $identifier): string
    {
        return Str::lower(trim($identifier)).'|127.0.0.1';
    }
}
