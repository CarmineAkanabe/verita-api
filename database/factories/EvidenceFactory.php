<?php

namespace Database\Factories;

use App\Enums\EvidenceFileType;
use App\Models\CaseRecord;
use App\Models\Evidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_record_id' => CaseRecord::factory(),
            'file_path' => 'evidence/' . fake()->uuid() . '.jpg',
            'file_type' => EvidenceFileType::IMAGE,
            'uploaded_at' => now(),
        ];
    }
}
