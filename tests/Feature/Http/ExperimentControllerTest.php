<?php

namespace Tests\Feature\Http;

use App\Enums\ExperimentStatus;
use App\Jobs\RunExperiment;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExperimentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_experiment_index(): void
    {
        $response = $this->get(route('experiments.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_only_the_authenticated_users_experiments(): void
    {
        $user = User::factory()->create();
        Experiment::factory()->for($user)->create(['name' => 'Visible experiment']);
        Experiment::factory()->create(['name' => 'Another user experiment']);

        $response = $this->actingAs($user)->get(route('experiments.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('experiments/index')
            ->has('experiments.data', 1)
            ->where('experiments.data.0.name', 'Visible experiment')
            ->where(
                'default_instructions.sender',
                config('agent.default_instructions.sender'),
            )
            ->where(
                'default_instructions.receiver',
                config('agent.default_instructions.receiver'),
            ));
    }

    public function test_valid_payload_creates_pending_experiment_without_starting_it(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Signal negotiation',
            'max_turns' => 4,
            'provider' => 'vertex',
            'sender_instructions' => '  Prefer spatial concepts.  ',
            'receiver_instructions' => '  Ask for clarification.  ',
        ]);

        $experiment = Experiment::query()->sole();
        $response->assertRedirect(route('experiments.show', $experiment));
        $this->assertSame($user->id, $experiment->user_id);
        $this->assertSame(ExperimentStatus::Pending, $experiment->status);
        $this->assertSame('fake', $experiment->provider);
        $this->assertSame(4, $experiment->max_turns);
        $this->assertSame('Prefer spatial concepts.', $experiment->sender_instructions);
        $this->assertSame('Ask for clarification.', $experiment->receiver_instructions);
        $this->assertNull($experiment->queued_at);
    }

    public function test_blank_agent_instructions_use_configured_defaults(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Default protocol',
            'max_turns' => 2,
            'sender_instructions' => '   ',
            'receiver_instructions' => '',
        ])->assertRedirect();

        $experiment = Experiment::query()->sole();
        $this->assertSame(
            config('agent.default_instructions.sender'),
            $experiment->sender_instructions,
        );
        $this->assertSame(
            config('agent.default_instructions.receiver'),
            $experiment->receiver_instructions,
        );
    }

    public function test_blank_max_turns_creates_an_unlimited_experiment(): void
    {
        config()->set('agent.maximum_turns', null);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Unlimited protocol',
            'max_turns' => '',
        ])->assertRedirect();

        $this->assertNull(Experiment::query()->sole()->max_turns);
    }

    public function test_blank_max_turns_is_rejected_when_an_environment_limit_is_configured(): void
    {
        config()->set('agent.maximum_turns', 90);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Missing limit',
            'max_turns' => '',
        ])->assertSessionHasErrors([
            'max_turns' => 'The max turns field is required.',
        ]);

        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_max_turns_can_be_unlimited_when_no_environment_limit_is_configured(): void
    {
        config()->set('agent.maximum_turns', null);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Beyond the configured limit',
            'max_turns' => 999,
        ])->assertRedirect();

        $this->assertSame(999, Experiment::query()->sole()->max_turns);
    }

    public function test_invalid_payload_returns_validation_messages_and_creates_nothing(): void
    {
        config()->set('agent.maximum_turns', 8);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('experiments.store'), [
            'name' => '',
            'max_turns' => 999,
        ]);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'max_turns' => 'The max turns field must not be greater than 8.',
        ]);
        $this->assertDatabaseCount('experiments', 0);
    }

    public function test_owner_can_view_detail_page(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('experiments.show', $experiment));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('experiments/show')
            ->where('experiment.id', $experiment->id)
            ->where('manual_run_turns', 20)
            ->has('messages', 0)
            ->has('vocabulary', 0)
            ->has('events', 0));
    }

    public function test_other_users_experiment_returns_404(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $experiment = Experiment::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)->get(route('experiments.show', $experiment));

        $response->assertNotFound();
    }

    public function test_missing_experiment_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('experiments.show', 999999));

        $response->assertNotFound();
    }

    public function test_authenticated_owner_can_queue_manual_execution(): void
    {
        Queue::fake([RunExperiment::class]);
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('experiments.run', $experiment));

        $response->assertRedirect();
        $this->assertNotNull($experiment->refresh()->queued_at);
        Queue::assertPushed(
            RunExperiment::class,
            fn (RunExperiment $job): bool => $job->experimentId === $experiment->id
                && $job->remainingTurns === 20,
        );
    }

    public function test_authenticated_owner_can_resume_a_paused_experiment(): void
    {
        Queue::fake([RunExperiment::class]);
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->paused()->create([
            'current_turn' => 12,
            'max_turns' => null,
        ]);

        $response = $this->actingAs($user)->post(route('experiments.run', $experiment));

        $response->assertRedirect();
        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Pending, $experiment->status);
        $this->assertNotNull($experiment->queued_at);
        Queue::assertPushed(
            RunExperiment::class,
            fn (RunExperiment $job): bool => $job->experimentId === $experiment->id
                && $job->remainingTurns === 20,
        );
    }

    public function test_paid_provider_cannot_run_while_feature_flag_is_disabled(): void
    {
        Queue::fake([RunExperiment::class]);
        config()->set('agent.paid_execution_enabled', false);
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create([
            'provider' => 'vertex',
        ]);

        $response = $this->actingAs($user)->post(route('experiments.run', $experiment));

        $response->assertSessionHasErrors([
            'experiment' => 'Paid AI execution is disabled by configuration.',
        ]);
        $this->assertNull($experiment->refresh()->queued_at);
        Queue::assertNothingPushed();
    }
}
