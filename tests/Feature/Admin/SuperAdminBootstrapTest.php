<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SuperAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_interactive_bootstrap_creates_one_active_super_admin_without_exposing_the_password(): void
    {
        $this->artisan('users:bootstrap-super-admin')
            ->expectsQuestion('Email (leave blank if unused)', 'root@example.test')
            ->expectsQuestion('Phone (leave blank if unused)', '')
            ->expectsQuestion('Full name', 'Root Administrator')
            ->expectsQuestion('Initial password', 'secure-bootstrap-password')
            ->expectsQuestion('Confirm initial password', 'secure-bootstrap-password')
            ->expectsOutputToContain('created or assigned successfully')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'root@example.test')->firstOrFail();
        $this->assertSame('ACTIVE', $user->account_status);
        $this->assertSame(['SUPER_ADMIN'], $user->roles()->pluck('code')->all());
        $this->assertTrue(Hash::check('secure-bootstrap-password', $user->password_hash));
        $this->assertSame('Root Administrator', $user->profile->full_name);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_bootstrap_can_assign_super_admin_to_a_password_verified_existing_user_without_replacing_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'password_hash' => Hash::make('existing-account-password'),
        ]);
        $user->profile()->create(['full_name' => 'Existing account']);
        $user->roles()->attach(Role::query()->where('code', 'RENTER')->value('id'), ['assigned_at' => now()]);

        $this->artisan('users:bootstrap-super-admin')
            ->expectsQuestion('Email (leave blank if unused)', 'existing@example.test')
            ->expectsQuestion('Phone (leave blank if unused)', '')
            ->expectsQuestion('Existing account password', 'existing-account-password')
            ->expectsConfirmation('Assign the SUPER_ADMIN role to the verified existing account?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(['RENTER', 'SUPER_ADMIN'], $user->fresh('roles')->roles->sortBy('code')->pluck('code')->values()->all());
        $this->assertTrue(Hash::check('existing-account-password', $user->fresh()->password_hash));
    }

    public function test_bootstrap_refuses_when_the_single_super_admin_already_exists(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::query()->where('code', 'SUPER_ADMIN')->value('id'), ['assigned_at' => now()]);

        $this->artisan('users:bootstrap-super-admin')
            ->expectsOutputToContain('Exactly one SUPER_ADMIN is already assigned')
            ->assertExitCode(1);

        $this->assertSame(1, Role::query()->where('code', 'SUPER_ADMIN')->firstOrFail()->users()->count());
    }

    public function test_concurrent_bootstrap_attempts_cannot_assign_two_super_admin_accounts(): void
    {
        DB::commit();
        DB::beginTransaction();
        Role::query()->where('code', 'SUPER_ADMIN')->lockForUpdate()->firstOrFail();

        $processes = [];
        $worker = <<<'PHP'
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$id = (int) $argv[2];
$userId = app(App\Services\SuperAdminBootstrapService::class)->createOrAssign(
    null,
    "bootstrap{$id}@example.test",
    null,
    "Bootstrap Admin {$id}",
    bin2hex(random_bytes(24)),
);
echo $userId === null ? 'REFUSED' : 'CREATED';
PHP;

        foreach ([1, 2] as $number) {
            $process = new Process([PHP_BINARY, '-r', $worker, base_path(), (string) $number], base_path());
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }

        usleep(200_000);

        DB::commit();

        foreach ($processes as $process) {
            $process->wait();
        }

        $assignments = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.code', 'SUPER_ADMIN')
            ->count();
        $this->assertSame(1, $assignments, implode("\n---\n", array_map(
            fn (Process $process): string => $process->getOutput().' STDERR: '.$process->getErrorOutput(),
            $processes,
        )));
        $this->assertSame(1, User::query()->count());
        $this->assertSame(2, count(array_filter($processes, fn (Process $process): bool => $process->isSuccessful())));
        $this->assertEqualsCanonicalizing(['CREATED', 'REFUSED'], array_map(fn (Process $process): string => $process->getOutput(), $processes));

        DB::table('user_roles')->delete();
        DB::table('user_profiles')->delete();
        DB::table('users')->delete();
    }
}
