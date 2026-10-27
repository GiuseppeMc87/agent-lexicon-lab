<?php

namespace Database\Factories;

use App\Models\Experiment;
use App\Models\VocabularyEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VocabularyEntry>
 */
class VocabularyEntryFactory extends Factory
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
            'symbol' => fake()->unique()->regexify('[a-z]{8}'),
        ];
    }
}
