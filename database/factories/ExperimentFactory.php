<?php

namespace Database\Factories;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Experiment>
 */
class ExperimentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'status' => ExperimentStatus::Pending,
            'provider' => 'fake',
            'model' => 'deterministic-local',
            'max_turns' => 3,
            'current_turn' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => ExperimentStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExperimentStatus::Completed,
            'current_turn' => 3,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
