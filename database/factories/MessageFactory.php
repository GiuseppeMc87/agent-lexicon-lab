<?php

namespace Database\Factories;

use App\Enums\AgentRole;
use App\Models\Experiment;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'experiment_id' => Experiment::factory(),
            'turn' => 1,
            'role' => AgentRole::Sender,
            'symbolic_content' => 'A message using [[signal-one]].',
            'decoded_content' => 'A message using shared signal one.',
        ];
    }
}
