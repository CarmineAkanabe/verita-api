<?php

namespace Database\Factories;

use App\Enums\PresenceStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
// use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => Role::DEPARTMENT_HEAD,
            'department_id' => Department::factory(),
            'staff_id' => null,
            'profile_picture' => null,
            'presence_status' => PresenceStatus::OFFLINE,
        ];
    }

    public function manager(): static
    {
        return $this->state(fn() => [
            'role' => Role::MANAGER,
            'department_id' => null,
            'staff_id' => 'STF-' . fake()->unique()->numerify('####'),
        ]);
    }
}
