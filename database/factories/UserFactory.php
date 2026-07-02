<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
            'organization_id' => Organization::factory(),
            'center_id' => null,
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function forCenter(Center $center): static
    {
        return $this->state(fn () => [
            'organization_id' => $center->organization_id,
            'center_id' => $center->id,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => [
            'must_change_password' => true,
        ]);
    }
}
