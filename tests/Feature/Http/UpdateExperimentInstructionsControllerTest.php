<?php

namespace Tests\Feature\Http;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UpdateExperimentInstructionsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_update_instructions_before_queueing(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('experiments.instructions.update', $experiment),
            [
                'sender_instructions' => '  Introduce one symbol at a time.  ',
                'receiver_instructions' => '  Prefer literal interpretations.  ',
            ],
        );

        $response->assertRedirect();
        $experiment->refresh();
        $this->assertSame('Introduce one symbol at a time.', $experiment->sender_instructions);
        $this->assertSame('Prefer literal interpretations.', $experiment->receiver_instructions);
    }

    public function test_queued_experiment_rejects_instruction_changes(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create([
            'sender_instructions' => 'Original sender brief.',
            'queued_at' => now(),
        ]);

        $response = $this->actingAs($user)->patch(
            route('experiments.instructions.update', $experiment),
            ['sender_instructions' => 'Changed after queueing.'],
        );

        $response->assertSessionHasErrors([
            'experiment' => 'Instructions can only be changed before the experiment is queued.',
        ]);
        $this->assertSame('Original sender brief.', $experiment->refresh()->sender_instructions);
    }

    public function test_started_experiment_rejects_instruction_changes(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create([
            'status' => ExperimentStatus::Running,
            'sender_instructions' => 'Original sender brief.',
        ]);

        $response = $this->actingAs($user)->patch(
            route('experiments.instructions.update', $experiment),
            ['sender_instructions' => 'Changed after starting.'],
        );

        $response->assertSessionHasErrors('experiment');
        $this->assertSame('Original sender brief.', $experiment->refresh()->sender_instructions);
    }

    public function test_instruction_length_is_limited_to_2000_characters(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('experiments.instructions.update', $experiment),
            ['sender_instructions' => str_repeat('a', 2001)],
        );

        $response->assertSessionHasErrors([
            'sender_instructions' => 'The sender instructions field must not be greater than 2000 characters.',
        ]);
        $this->assertNull($experiment->refresh()->sender_instructions);
    }

    public function test_other_users_experiment_returns_404(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $experiment = Experiment::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->patch(
            route('experiments.instructions.update', $experiment),
            ['sender_instructions' => 'Unauthorized change.'],
        );

        $response->assertNotFound();
        $this->assertNull($experiment->refresh()->sender_instructions);
    }
}
