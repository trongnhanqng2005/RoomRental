<?php

namespace Tests\Feature\Report;

use App\Models\ViewingSlot;
use Carbon\CarbonImmutable;

class LockedAccountEligibilityTest extends ReportFeatureTestCase
{
    public function test_locked_landlord_listings_disappear_from_public_search_and_detail(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord, 'Tin của chủ trọ bị khóa');
        $landlord->forceFill(['account_status' => 'LOCKED'])->save();

        $this->get(route('public.listings.index'))
            ->assertOk()
            ->assertDontSee('Tin của chủ trọ bị khóa');
        $this->get(route('public.listings.show', $listing))
            ->assertNotFound()
            ->assertDontSee('Tin của chủ trọ bị khóa');
    }

    public function test_locked_landlord_listing_cannot_accept_a_new_appointment(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $listing = $this->listing($landlord);
        $renter = $this->userWithRoles(['RENTER']);
        $start = CarbonImmutable::now(ViewingSlot::TIMEZONE)->addHours(3);
        $slot = ViewingSlot::query()->create([
            'listing_id' => $listing->id,
            'viewing_date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->addHour()->format('H:i:s'),
            'status' => 'OPEN',
        ]);
        $landlord->forceFill(['account_status' => 'LOCKED'])->save();

        $this->actingAs($renter)
            ->from(route('public.listings.show', $listing))
            ->post(route('appointments.store', $slot))
            ->assertRedirect(route('public.listings.show', $listing))
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_next_web_request_logs_out_locked_user_invalidates_session_and_regenerates_csrf_token(): void
    {
        $lockedUser = $this->userWithRoles(['RENTER']);
        $lockedUser->forceFill(['account_status' => 'LOCKED'])->save();
        $this->actingAs($lockedUser)->withSession([
            '_token' => 'known-before-lock-token',
            'session_marker' => true,
        ]);
        $oldSessionId = $this->app['session']->getId();

        $response = $this->get(route('profile.show'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertNotSame($oldSessionId, $response->getSession()->getId());
        $this->assertNotSame('known-before-lock-token', $response->getSession()->token());
        $this->assertFalse($response->getSession()->has('session_marker'));
    }

    public function test_locked_admin_session_is_denied_even_on_a_non_role_gated_web_route(): void
    {
        $lockedAdmin = $this->userWithRoles(['ADMIN']);
        $lockedAdmin->forceFill(['account_status' => 'LOCKED'])->save();

        $this->actingAs($lockedAdmin)
            ->get(route('public.listings.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_locked_session_login_and_logout_requests_terminate_at_the_login_page(): void
    {
        $lockedUser = $this->userWithRoles(['RENTER']);
        $lockedUser->forceFill(['account_status' => 'LOCKED'])->save();

        $this->actingAs($lockedUser)
            ->followingRedirects()
            ->get(route('login'))
            ->assertOk()
            ->assertSee(__('ui.login.heading'));
        $this->assertGuest();

        $this->actingAs($lockedUser)
            ->followingRedirects()
            ->post(route('logout'))
            ->assertOk()
            ->assertSee(__('ui.login.heading'));
        $this->assertGuest();
    }

    public function test_active_authenticated_users_are_not_affected_by_account_status_middleware(): void
    {
        $activeUser = $this->userWithRoles(['RENTER']);

        $this->actingAs($activeUser)
            ->get(route('profile.show'))
            ->assertOk();
        $this->get(route('login'))->assertRedirect('/');
        $this->assertAuthenticatedAs($activeUser);
    }
}
