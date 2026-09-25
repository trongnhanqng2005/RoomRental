<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UserAdministrationService
{
    public function __construct(
        private AccountLockService $accountLockService,
        private AppointmentService $appointmentService,
    ) {}

    /**
     * @param  array{q?: ?string, role?: ?string, status?: ?string}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->select(['users.id', 'users.email', 'users.phone', 'users.account_status', 'users.created_at'])
            ->with([
                'profile:user_id,full_name',
                'roles:id,code,name',
            ]);

        if (($filters['q'] ?? null) !== null && $filters['q'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function (Builder $search) use ($pattern): void {
                $search->whereHas('profile', fn (Builder $profile) => $profile->whereRaw("full_name LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("users.phone LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (($filters['role'] ?? null) !== null) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('code', $filters['role']));
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('account_status', $filters['status']);
        }

        return $query->orderByDesc('users.created_at')
            ->orderByDesc('users.id')
            ->paginate(10)
            ->appends($filters);
    }

    public function detail(User $requested): User
    {
        return User::query()
            ->select(['users.id', 'users.email', 'users.phone', 'users.account_status', 'users.created_at'])
            ->with(['profile:user_id,full_name', 'roles:id,code,name'])
            ->findOrFail($requested->id);
    }

    /** @return array{changed: bool, appointment_notifications: array<int, array{context: array<string, mixed>, renter_id: int, cancellation_reason: string}>} */
    public function lock(User $requested, User $actor): array
    {
        $this->authorizeActor($actor);
        $result = DB::transaction(function () use ($requested, $actor): array {
            $target = User::query()->lockForUpdate()->findOrFail($requested->id);
            $this->authorizeTarget($actor, $target, 'account');
            $result = $this->accountLockService->lock($target, $actor);

            if ($result['changed']) {
                $this->audit($actor, $target, 'user.locked');
            }

            return $result + ['target_id' => $target->id];
        }, 3);

        if ($result['changed']) {
            $this->notify($result['target_id'], 'ACCOUNT_LOCKED', __('ui.notifications.account_locked_title'), __('ui.notifications.account_locked_message'), 'user', $result['target_id']);
            $this->appointmentService->notifyAutoCancelledAppointments($result['appointment_notifications']);
        }

        return $result;
    }

    public function unlock(User $requested, User $actor): void
    {
        $this->authorizeActor($actor);
        $targetId = DB::transaction(function () use ($requested, $actor): ?int {
            $target = User::query()->lockForUpdate()->findOrFail($requested->id);
            $this->authorizeTarget($actor, $target, 'account');

            if ($target->account_status === 'ACTIVE') {
                return null;
            }

            if ($target->account_status !== 'LOCKED') {
                throw new RuntimeException('The account status is not supported.');
            }

            $target->forceFill(['account_status' => 'ACTIVE'])->save();
            $this->audit($actor, $target, 'user.unlocked');

            return (int) $target->id;
        }, 3);

        if ($targetId !== null) {
            $this->notify($targetId, 'ACCOUNT_UNLOCKED', __('ui.notifications.account_unlocked_title'), __('ui.notifications.account_unlocked_message'), 'user', $targetId);
        }
    }

    public function resetPassword(User $requested, User $actor): string
    {
        $this->authorizeActor($actor);
        $reset = DB::transaction(function () use ($requested, $actor): array {
            $target = User::query()->lockForUpdate()->findOrFail($requested->id);
            $this->authorizeTarget($actor, $target, 'password');

            $temporaryPassword = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $target->forceFill([
                'password_hash' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ])->save();
            $this->audit($actor, $target, 'user.password_reset');

            return ['target_id' => (int) $target->id, 'temporary_password' => $temporaryPassword];
        }, 3);

        return $reset['temporary_password'];
    }

    public function promote(User $requested, User $actor): void
    {
        $this->authorizeSuperAdmin($actor);
        $targetId = DB::transaction(function () use ($requested, $actor): ?int {
            $target = User::query()->lockForUpdate()->findOrFail($requested->id);
            $this->authorizeTarget($actor, $target, 'role');

            if ($target->hasRole('ADMIN')) {
                return null;
            }

            $role = Role::query()->where('code', 'ADMIN')->firstOrFail();
            $target->roles()->attach($role->id, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
            $this->audit($actor, $target, 'user.admin_promoted');

            return (int) $target->id;
        }, 3);

        if ($targetId !== null) {
            $this->notify($targetId, 'ADMIN_PROMOTED', __('ui.notifications.admin_promoted_title'), __('ui.notifications.admin_promoted_message'), 'user', $targetId);
        }
    }

    public function revokeAdmin(User $requested, User $actor): void
    {
        $this->authorizeSuperAdmin($actor);
        $targetId = DB::transaction(function () use ($requested, $actor): ?int {
            $target = User::query()->lockForUpdate()->findOrFail($requested->id);
            $this->authorizeTarget($actor, $target, 'role');

            if (! $target->hasRole('ADMIN')) {
                return null;
            }

            $role = Role::query()->where('code', 'ADMIN')->firstOrFail();
            $target->roles()->detach($role->id);
            $this->audit($actor, $target, 'user.admin_revoked');

            return (int) $target->id;
        }, 3);

        if ($targetId !== null) {
            $this->notify($targetId, 'ADMIN_REVOKED', __('ui.notifications.admin_revoked_title'), __('ui.notifications.admin_revoked_message'), 'user', $targetId);
        }
    }

    private function authorizeActor(User $actor): void
    {
        if (! $actor->hasAnyRole('ADMIN', 'SUPER_ADMIN')) {
            throw new AuthorizationException;
        }
    }

    private function authorizeSuperAdmin(User $actor): void
    {
        if (! $actor->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException;
        }
    }

    private function authorizeTarget(User $actor, User $target, string $operation): void
    {
        if ((int) $target->id === (int) $actor->id || $target->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException;
        }

        if ($operation === 'role' && ! $actor->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException;
        }

        if ($target->hasRole('ADMIN') && ! $actor->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException;
        }
    }

    private function audit(User $actor, User $target, string $action): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => User::class,
            'entity_id' => $target->id,
            'created_at' => now(),
        ]);
    }

    private function notify(int $recipientId, string $type, string $title, string $message, string $entityType, int $entityId): void
    {
        try {
            AppNotification::query()->create([
                'user_id' => $recipientId,
                'notification_type' => $type,
                'title' => Str::limit($title, 255, ''),
                'message' => Str::limit($message, 1000, ''),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'read_at' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            try {
                Log::warning('User administration notification could not be created.', [
                    'user_id' => $recipientId,
                    'notification_type' => $type,
                ]);
            } catch (Throwable) {
                // Committed user administration changes must remain successful.
            }
        }
    }
}
