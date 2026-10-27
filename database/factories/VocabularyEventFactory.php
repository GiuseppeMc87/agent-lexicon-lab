<?php

namespace Database\Factories;

use App\Enums\AgentRole;
use App\Enums\VocabularyEventType;
use App\Models\Experiment;
use App\Models\VocabularyEntry;
use App\Models\VocabularyEvent;
use App\Models\VocabularyRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VocabularyEvent>
 */
class VocabularyEventFactory extends Factory
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
            'vocabulary_entry_id' => VocabularyEntry::factory(),
            'vocabulary_revision_id' => VocabularyRevision::factory(),
            'message_id' => null,
            'type' => VocabularyEventType::Created,
            'proposer_role' => AgentRole::Sender,
            'turn' => 1,
            'occurred_at' => now(),
        ];
    }
}
