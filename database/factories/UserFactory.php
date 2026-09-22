<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password_hash' => static::$password ??= Hash::make('password'),
            'account_status' => 'ACTIVE',
            'failed_login_count' => 0,
            'login_blocked_until' => null,
            'must_change_password' => false,
            'last_login_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'ACTIVE',
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_status' => 'LOCKED',
        ]);
    }
}
