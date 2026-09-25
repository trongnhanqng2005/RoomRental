<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SuperAdminBootstrapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'users:bootstrap-super-admin';

    protected $description = 'Create or identify the one initial Super Admin account.';

    public function handle(SuperAdminBootstrapService $bootstrap): int
    {
        $initialCount = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.code', 'SUPER_ADMIN')
            ->count();

        if ($initialCount === 1) {
            $this->error('Exactly one SUPER_ADMIN is already assigned. Bootstrap was not changed.');

            return self::FAILURE;
        }

        if ($initialCount > 1) {
            throw new RuntimeException('SUPER_ADMIN singleton invariant is violated: more than one assignment exists.');
        }

        $email = $this->nullableInput('Email');
        $phone = $this->nullableInput('Phone');

        if ($email === null && $phone === null) {
            $this->error('Provide an email address, a phone number, or both.');

            return self::FAILURE;
        }

        if ($email !== null && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
            $this->error('The email address is invalid.');

            return self::FAILURE;
        }

        if ($phone !== null && (mb_strlen($phone) > 20 || ! preg_match('/^\+?[0-9]+$/', $phone))) {
            $this->error('The phone number is invalid.');

            return self::FAILURE;
        }

        $matches = User::query()->where(function ($query) use ($email, $phone): void {
            if ($email !== null) {
                $query->orWhere('email', $email);
            }
            if ($phone !== null) {
                $query->orWhere('phone', $phone);
            }
        })->get();

        if ($matches->count() > 1) {
            $this->error('The supplied identifiers belong to different accounts.');

            return self::FAILURE;
        }

        $existing = $matches->first();
        $fullName = null;
        $password = null;

        if ($existing !== null) {
            $currentPassword = $this->secret('Existing account password');

            if (! Hash::check($currentPassword, $existing->password_hash)) {
                $this->error('The existing account password did not match.');

                return self::FAILURE;
            }

            if (! $existing->profile()->exists()) {
                $this->error('The existing account profile is missing.');

                return self::FAILURE;
            }

            if ($existing->account_status !== 'ACTIVE') {
                $this->error('The existing account must be ACTIVE before Super Admin bootstrap.');

                return self::FAILURE;
            }

            if (! $this->confirm('Assign the SUPER_ADMIN role to the verified existing account?', false)) {
                return self::SUCCESS;
            }
        } else {
            $fullName = trim((string) $this->ask('Full name'));

            if ($fullName === '' || mb_strlen($fullName) > 150) {
                $this->error('A full name of at most 150 characters is required.');

                return self::FAILURE;
            }

            $password = $this->secret('Initial password');
            $confirmation = $this->secret('Confirm initial password');

            if (mb_strlen($password) < 8 || ! hash_equals($password, $confirmation)) {
                $this->error('The password must contain at least 8 characters and match its confirmation.');

                return self::FAILURE;
            }
        }

        $userId = $bootstrap->createOrAssign(
            $existing?->id,
            $email,
            $phone,
            $fullName,
            $password,
            $existing?->password_hash,
        );

        if ($userId === null) {
            $this->error('Another bootstrap operation assigned SUPER_ADMIN while credentials were being collected.');

            return self::FAILURE;
        }

        Log::notice('The initial Super Admin account was bootstrapped.', ['user_id' => $userId]);
        $this->info('The initial SUPER_ADMIN account was created or assigned successfully.');

        return self::SUCCESS;
    }

    private function nullableInput(string $label): ?string
    {
        $value = trim((string) $this->ask($label.' (leave blank if unused)'));

        return $value === '' ? null : $value;
    }
}
