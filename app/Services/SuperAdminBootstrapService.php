<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminBootstrapService
{
    public function createOrAssign(
        ?int $existingUserId,
        ?string $email,
        ?string $phone,
        ?string $fullName,
        ?string $password,
        ?string $expectedExistingPasswordHash = null,
    ): ?int {
        return DB::transaction(function () use ($existingUserId, $email, $phone, $fullName, $password, $expectedExistingPasswordHash): ?int {
            $role = Role::query()->where('code', 'SUPER_ADMIN')->lockForUpdate()->first();

            if ($role === null) {
                throw new RuntimeException('The SUPER_ADMIN role is not seeded.');
            }

            $count = DB::table('user_roles')->where('role_id', $role->id)->count();

            if ($count === 1) {
                return null;
            }

            if ($count > 1) {
                throw new RuntimeException('SUPER_ADMIN singleton invariant is violated: more than one assignment exists.');
            }

            if ($existingUserId !== null) {
                $user = User::query()->lockForUpdate()->findOrFail($existingUserId);

                if (
                    ($email !== null && $user->email !== $email)
                    || ($phone !== null && $user->phone !== $phone)
                    || $expectedExistingPasswordHash === null
                    || ! hash_equals($expectedExistingPasswordHash, $user->password_hash)
                ) {
                    throw new RuntimeException('The verified account credentials changed during bootstrap.');
                }

                if ($user->account_status !== 'ACTIVE') {
                    throw new RuntimeException('The selected account must be ACTIVE before Super Admin bootstrap.');
                }
            } else {
                $user = new User;
                $user->forceFill([
                    'email' => $email,
                    'phone' => $phone,
                    'password_hash' => Hash::make((string) $password),
                    'account_status' => 'ACTIVE',
                    'failed_login_count' => 0,
                    'login_blocked_until' => null,
                    'must_change_password' => false,
                    'last_login_at' => null,
                ])->save();
                $user->profile()->create(['full_name' => $fullName]);
            }

            $user->roles()->attach($role->id, [
                'assigned_by' => null,
                'assigned_at' => now(),
            ]);

            return (int) $user->id;
        }, 3);
    }
}
