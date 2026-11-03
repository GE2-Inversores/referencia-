<?php

namespace Database\Factories;

use App\Models\IdentificationType;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identification_type_id' => IdentificationType::factory(),
            'identification_number' => fake()->unique()->numerify('##########'),
            'user_name' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'sur_name' => fake()->optional()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'job_title' => fake()->jobTitle(),
            'is_active' => true,
            'must_change_password' => false,
            'must_update_profile' => false,
            'failed_login_attempts' => 0,
            'password' => static::$password ??= Hash::make('admin123'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'identification_number' => '1000000000',
            'user_name' => 'superadmin',
            'first_name' => 'Super',
            'middle_name' => null,
            'last_name' => 'Administrador',
            'sur_name' => null,
            'email' => env('SUPERADMIN_EMAIL', 'superadmin@example.com'),
            'job_title' => 'Super administrador',
            'must_change_password' => true,
            'must_update_profile' => true,
            'failed_login_attempts' => 0,
            'password' => Hash::make('admin123'),
        ]);
    }
}
