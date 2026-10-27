<?php

namespace Database\Factories;

use App\Enums\AgentRole;
use App\Models\VocabularyEntry;
use App\Models\VocabularyRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VocabularyRevision>
 */
class VocabularyRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vocabulary_entry_id' => VocabularyEntry::factory(),
            'message_id' => null,
            'version' => 1,
            'meaning' => fake()->sentence(),
            'description' => fake()->sentence(),
            'proposer_role' => AgentRole::Sender,
            'proposed_in_turn' => 1,
        ];
    }
}
