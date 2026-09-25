<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_and_super_admin_can_list_and_search_users_without_exposing_security_fields(): void
    {
        $target = $this->userWithRoles(['RENTER', 'LANDLORD'], 'Nguyễn Văn An', [
            'email' => 'an@example.test',
            'phone' => '0901234567',
        ]);

        $this->actingAs($this->userWithRoles(['ADMIN']))
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('Nguyễn Văn An')
            ->assertDontSee('password_hash')
            ->assertDontSee('contact_address');

        foreach (['ADMIN', 'SUPER_ADMIN'] as $role) {
            $response = $this->actingAs($this->userWithRoles([$role]))
                ->get(route('admin.users.index', ['q' => '0901234567']))
                ->assertOk()
                ->assertSee('Nguyễn Văn An')
                ->assertDontSee('password_hash')
                ->assertDontSee('failed_login_count')
                ->assertDontSee('contact_address');

            $users = $response->viewData('users');
            $this->assertSame(1, $users->total());
            $this->assertTrue($users->first()->relationLoaded('roles'));
            $this->assertTrue($users->first()->relationLoaded('profile'));
            $this->assertArrayNotHasKey('password_hash', $users->first()->getAttributes());
            $this->assertSame(['LANDLORD', 'RENTER'], $target->roles()->orderBy('code')->pluck('code')->all());
        }
    }

    public function test_non_admin_users_cannot_view_user_management(): void
    {
        foreach ([['RENTER'], ['LANDLORD'], ['RENTER', 'LANDLORD']] as $roles) {
            $this->actingAs($this->userWithRoles($roles))
                ->get(route('admin.users.index'))
                ->assertForbidden();
        }
    }

    public function test_literal_wildcards_role_and_status_filters_pagination_and_sorting(): void
    {
        $literal = $this->userWithRoles(['RENTER'], 'Tài khoản %_ đặc biệt');
        $this->userWithRoles(['RENTER'], 'Tài khoản xy đặc biệt');
        $locked = $this->userWithRoles(['RENTER'], 'Tài khoản khóa', ['account_status' => 'LOCKED']);
        $this->userWithRoles(['LANDLORD'], 'Chủ trọ khác');
        $admin = $this->userWithRoles(['ADMIN']);

        $literalResponse = $this->actingAs($admin)
            ->get(route('admin.users.index', ['q' => '%_']))
            ->assertOk()
            ->assertSee('Tài khoản %_ đặc biệt')
            ->assertDontSee('Tài khoản xy đặc biệt');
        $this->assertSame(1, $literalResponse->viewData('users')->total());

        $filtered = $this->get(route('admin.users.index', ['role' => 'RENTER', 'status' => 'LOCKED']))
            ->assertOk()
            ->viewData('users');
        $this->assertSame([$locked->id], $filtered->getCollection()->modelKeys());

        foreach (range(1, 11) as $number) {
            $this->userWithRoles(['RENTER'], 'Người thuê '.$number);
        }

        $firstPage = $this->get(route('admin.users.index', ['role' => 'RENTER']))
            ->assertOk()
            ->viewData('users');
        $secondPageResponse = $this->get(route('admin.users.index', ['role' => 'RENTER', 'page' => 2, 'unvalidated' => 'ignored']))
            ->assertOk();
        $secondPage = $secondPageResponse->viewData('users');

        $this->assertSame(10, $firstPage->count());
        $this->assertSame(14, $firstPage->total());
        $this->assertSame(4, $secondPage->count());
        $this->assertGreaterThan($secondPage->first()->id, $firstPage->last()->id);
        $secondPageResponse->assertSee('role=RENTER', false);
        $secondPageResponse->assertDontSee('unvalidated=ignored', false);
        $this->assertSame(1, $literal->roles()->where('code', 'RENTER')->count());
    }

    public function test_admin_lock_is_idempotent_and_audited_once(): void
    {
        $target = $this->userWithRoles(['RENTER']);
        $admin = $this->userWithRoles(['ADMIN']);

        $this->actingAs($admin)->patch(route('admin.users.lock', $target))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.users.lock', $target))->assertRedirect();

        $this->assertSame('LOCKED', $target->fresh()->account_status);
        $this->assertSame(1, AuditLog::query()->where('action', 'user.locked')->count());
    }

    public function test_admin_cannot_mutate_admin_or_super_admin_but_super_admin_can_manage_admin(): void
    {
        $admin = $this->userWithRoles(['ADMIN']);
        $superAdmin = $this->userWithRoles(['SUPER_ADMIN']);
        $ordinaryAdmin = $this->userWithRoles(['ADMIN']);
        $eligible = $this->userWithRoles(['RENTER', 'LANDLORD']);

        $this->actingAs($admin)
            ->patch(route('admin.users.lock', $ordinaryAdmin))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('admin.users.password-reset', $ordinaryAdmin))
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('admin.users.lock', $superAdmin))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('admin.users.promote', $eligible))
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete(route('admin.users.revoke', $ordinaryAdmin))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->post(route('admin.users.promote', $eligible))
            ->assertRedirect();

        $this->assertSame(['ADMIN', 'LANDLORD', 'RENTER'], $eligible->fresh('roles')->roles->sortBy('code')->pluck('code')->values()->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.admin_promoted')->count());
    }

    public function test_admin_and_super_admin_cannot_mutate_their_own_accounts(): void
    {
        foreach (['ADMIN', 'SUPER_ADMIN'] as $role) {
            $actor = $this->userWithRoles([$role]);
            $this->actingAs($actor)
                ->patch(route('admin.users.lock', $actor))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('admin.users.password-reset', $actor))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('admin.users.promote', $actor))
                ->assertForbidden();
            $this->actingAs($actor)
                ->delete(route('admin.users.revoke', $actor))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_super_admin_can_lock_admin_and_reset_admin_password(): void
    {
        $superAdmin = $this->userWithRoles(['SUPER_ADMIN']);
        $admin = $this->userWithRoles(['ADMIN']);
        $originalHash = $admin->password_hash;

        $this->actingAs($superAdmin)->patch(route('admin.users.lock', $admin))->assertRedirect();
        $resetResponse = $this->actingAs($superAdmin)
            ->post(route('admin.users.password-reset', $admin))
            ->assertRedirect();

        $this->assertSame('LOCKED', $admin->fresh()->account_status);
        $this->assertNotSame($originalHash, $admin->fresh()->password_hash);
        $this->assertTrue($admin->fresh()->must_change_password);
        $this->assertIsString($resetResponse->getSession()->get('temporary_password'));
        $this->assertSame(1, AuditLog::query()->where('action', 'user.locked')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.password_reset')->count());
    }

    public function test_password_reset_sets_forced_change_and_only_flashes_plaintext_once(): void
    {
        $target = $this->userWithRoles(['RENTER']);
        $admin = $this->userWithRoles(['ADMIN']);
        $originalHash = $target->password_hash;

        $response = $this->actingAs($admin)
            ->post(route('admin.users.password-reset', $target))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertNotSame($originalHash, $target->fresh()->password_hash);
        $this->assertTrue($target->fresh()->must_change_password);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user.password_reset',
            'entity_type' => User::class,
            'entity_id' => $target->id,
        ]);
        $temporaryPassword = $response->getSession()->get('temporary_password');
        $this->assertIsString($temporaryPassword);
        $this->assertTrue(Hash::check($temporaryPassword, $target->fresh()->password_hash));
        $detail = $this->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee($temporaryPassword);
        $this->assertStringContainsString('no-store', $detail->headers->get('Cache-Control'));
        $this->get(route('admin.users.show', $target))->assertDontSee($temporaryPassword);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => $temporaryPassword]);
    }

    public function test_audit_failure_rolls_back_password_reset_and_lock(): void
    {
        $target = $this->userWithRoles(['RENTER']);
        $admin = $this->userWithRoles(['ADMIN']);
        $originalHash = $target->password_hash;
        $fired = false;
        AuditLog::creating(function (AuditLog $audit) use (&$fired): void {
            if (in_array($audit->action, ['user.password_reset', 'user.locked'], true)) {
                $fired = true;
                throw new RuntimeException('Forced audit failure.');
            }
        });

        try {
            $this->actingAs($admin)->post(route('admin.users.password-reset', $target))->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertTrue($fired);
        $this->assertSame($originalHash, $target->fresh()->password_hash);
        $this->assertFalse($target->fresh()->must_change_password);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_super_admin_revokes_only_admin_role_and_repeated_actions_do_not_duplicate_audit_or_notification(): void
    {
        $actor = $this->userWithRoles(['SUPER_ADMIN']);
        $target = $this->userWithRoles(['ADMIN', 'RENTER', 'LANDLORD']);

        $this->actingAs($actor)->delete(route('admin.users.revoke', $target))->assertRedirect();
        $this->actingAs($actor)->delete(route('admin.users.revoke', $target))->assertRedirect();

        $this->assertSame(['LANDLORD', 'RENTER'], $target->fresh('roles')->roles->sortBy('code')->pluck('code')->values()->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.admin_revoked')->count());
        $this->assertSame(1, DB::table('notifications')->where('notification_type', 'ADMIN_REVOKED')->count());
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
