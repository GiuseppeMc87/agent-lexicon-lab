<?php

namespace Tests\Feature\Http;

use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CloneExperimentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_clone_experiment_into_editable_pending_copy(): void
    {
        config()->set('agent.driver', 'vertex');
        config()->set('agent.vertex.model', 'gemini-test');
        $user = User::factory()->create();
        $source = Experiment::factory()->for($user)->completed()->create([
            'name' => 'Spatial negotiation',
            'sender_instructions' => 'Prefer spatial concepts.',
            'receiver_instructions' => 'Challenge ambiguous symbols.',
            'max_turns' => 3,
        ]);

        $response = $this->actingAs($user)->post(route('experiments.clone', $source));

        $clone = Experiment::query()->whereKeyNot($source->id)->sole();
        $response->assertRedirect(route('experiments.show', $clone));
        $this->assertSame($user->id, $clone->user_id);
        $this->assertSame('Copy of Spatial negotiation', $clone->name);
        $this->assertSame(ExperimentStatus::Pending, $clone->status);
        $this->assertSame('vertex', $clone->provider);
        $this->assertSame('gemini-test', $clone->model);
        $this->assertSame('Prefer spatial concepts.', $clone->sender_instructions);
        $this->assertSame('Challenge ambiguous symbols.', $clone->receiver_instructions);
        $this->assertSame(3, $clone->max_turns);
        $this->assertSame(0, $clone->current_turn);
        $this->assertNull($clone->queued_at);
        $this->assertNull($clone->started_at);
    }

    public function test_other_users_experiment_returns_404(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Experiment::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->post(route('experiments.clone', $source));

        $response->assertNotFound();
        $this->assertDatabaseCount('experiments', 1);
    }
}
