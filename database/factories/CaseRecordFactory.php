<?php

namespace Database\Factories;

use App\Enums\CaseCategory;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<CaseRecord>
 */
class CaseRecordFactory extends Factory
{
    protected $model = CaseRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tracking_pin_hash' => Hash::make((string) fake()->numberBetween(100000, 999999)),
            'department_id' => Department::factory(),
            'category' => CaseCategory::FRAUD,
            'description' => fake()->paragraphs(3, true),
            'purpose_of_transaction' => fake()->sentence(),
            'amount_involved' => fake()->randomFloat(2, 5000, 500000),
            'person_involved' => fake()->name(),
            'transaction_date' => fake()->dateTimeBetween('-2 months', 'now'),
            'status' => CaseStatus::SUBMITTED,
            'assigned_to' => null,
            'concerns_department_head' => false,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn() => [
            'status' => CaseStatus::RESOLVED,
            'resolved_at' => now(),
            'resolution_summary' => fake()->sentence(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn() => [
            'status' => CaseStatus::DISMISSED,
            'resolved_at' => now(),
            'resolution_summary' => fake()->sentence(),
        ]);
    }
}
