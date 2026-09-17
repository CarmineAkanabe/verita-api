<?php

namespace Database\Factories;

use App\Enums\SenderType;
use App\Models\CaseRecord;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_record_id' => CaseRecord::factory(),
            'sender_type' => fake()->randomElement(SenderType::cases()),
            'content' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }
}
