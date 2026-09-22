<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = null;

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class, 'landlord_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->hasAnyRole($role);
    }

    public function hasAnyRole(array|string ...$roles): bool
    {
        $roleCodes = [];

        foreach ($roles as $role) {
            array_push($roleCodes, ...(array) $role);
        }

        if ($roleCodes === []) {
            return false;
        }

        return $this->roles()->whereIn('code', array_unique($roleCodes))->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'login_blocked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
