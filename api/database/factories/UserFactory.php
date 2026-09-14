<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Staff,
            'phone' => '2557'.fake()->numerify('########'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Owner,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Staff,
        ]);
    }

    public function agent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Agent,
        ]);
    }

    /**
     * Platform administrators sit above tenancy and have no tenant_id.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => null,
            'role' => UserRole::PlatformAdmin,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
