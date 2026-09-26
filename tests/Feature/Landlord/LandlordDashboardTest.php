<?php

namespace Tests\Feature\Landlord;

use Tests\Feature\Report\ReportFeatureTestCase;

class LandlordDashboardTest extends ReportFeatureTestCase
{
    public function test_landlord_dashboard_requires_landlord_capability(): void
    {
        $this->get('/landlord/dashboard')->assertRedirect('/login');

        $renter = $this->userWithRoles(['RENTER']);
        $this->actingAs($renter)->get('/landlord/dashboard')->assertForbidden();

        $landlord = $this->userWithRoles(['LANDLORD']);
        $this->actingAs($landlord)->get('/landlord/dashboard')->assertOk();

        $dualCapability = $this->userWithRoles(['RENTER', 'LANDLORD']);
        $this->actingAs($dualCapability)->get('/landlord/dashboard')->assertOk();

        $admin = $this->userWithRoles(['ADMIN']);
        $this->actingAs($admin)->get('/landlord/dashboard')->assertForbidden();

        $adminLandlord = $this->userWithRoles(['ADMIN', 'LANDLORD']);
        $this->actingAs($adminLandlord)
            ->get('/landlord/dashboard')
            ->assertOk()
            ->assertSee(route('admin.dashboard'))
            ->assertSee(route('landlord.dashboard'));

        $superAdmin = $this->userWithRoles(['SUPER_ADMIN']);
        $this->actingAs($superAdmin)->get('/landlord/dashboard')->assertForbidden();

        $superAdminLandlord = $this->userWithRoles(['SUPER_ADMIN', 'LANDLORD']);
        $this->actingAs($superAdminLandlord)->get('/landlord/dashboard')->assertOk();
    }

    public function test_landlord_dashboard_counts_only_its_non_deleted_inventory_and_views(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $otherLandlord = $this->userWithRoles(['LANDLORD']);

        $this->listing($landlord, 'Available hidden expired pending', [
            'occupancy_status' => 'AVAILABLE',
            'visibility_status' => 'SUSPENDED',
            'moderation_status' => 'PENDING',
            'expires_at' => now()->subDay(),
            'view_count' => 13,
        ]);
        $this->listing($landlord, 'Rented rejected hidden', [
            'occupancy_status' => 'RENTED',
            'visibility_status' => 'HIDDEN',
            'moderation_status' => 'REJECTED',
            'expires_at' => now()->subDays(2),
            'view_count' => 8,
        ]);
        $this->listing($landlord, 'Deleted listing', [
            'deleted_at' => now(),
            'view_count' => 1000,
        ]);
        $this->listing($otherLandlord, 'Another landlord listing', ['view_count' => 500]);

        $this->actingAs($landlord)
            ->get('/landlord/dashboard')
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics === [
                'total_listings' => 2,
                'available_rooms' => 1,
                'rented_rooms' => 1,
                'waiting_appointments' => 0,
                'total_views' => 21,
            ]);
    }

    public function test_landlord_waiting_appointment_metric_counts_only_pending_own_non_deleted_listings(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);
        $otherLandlord = $this->userWithRoles(['LANDLORD']);
        $renter = $this->userWithRoles(['RENTER']);

        $owned = $this->listing($landlord, 'Owned');
        $deleted = $this->listing($landlord, 'Deleted', ['deleted_at' => now()]);
        $other = $this->listing($otherLandlord, 'Other landlord');

        $this->appointment($owned, $renter, 3, 'PENDING');
        $this->appointment($owned, $renter, 4, 'ACCEPTED');
        $this->appointment($owned, $renter, 5, 'REJECTED');
        $this->appointment($owned, $renter, 8, 'CANCELLED');
        $this->appointment($owned, $renter, 9, 'COMPLETED');
        $this->appointment($owned, $renter, 10, 'AUTO_CANCELLED');
        $this->appointment($deleted, $renter, 6, 'PENDING');
        $this->appointment($other, $renter, 7, 'PENDING');

        $this->actingAs($landlord)
            ->get('/landlord/dashboard')
            ->assertOk()
            ->assertViewHas('metrics.waiting_appointments', 1);
    }

    public function test_landlord_dashboard_exposes_only_existing_management_links_and_five_metrics(): void
    {
        $landlord = $this->userWithRoles(['LANDLORD']);

        $this->actingAs($landlord)
            ->get('/landlord/dashboard')
            ->assertOk()
            ->assertSee(route('landlord.listings.index'))
            ->assertSee(route('landlord.appointments.index'))
            ->assertViewHas('metrics', fn (array $metrics): bool => array_keys($metrics) === [
                'total_listings',
                'available_rooms',
                'rented_rooms',
                'waiting_appointments',
                'total_views',
            ]);
    }
}
