<?php

namespace Tests\Feature\Http;

use App\Enums\AgentRole;
use App\Models\Experiment;
use App\Models\User;
use App\Services\ExperimentOrchestrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExperimentUpdateControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cursor_returns_only_new_messages_and_their_vocabulary(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create(['max_turns' => 2]);
        app(ExperimentOrchestrator::class)->run($experiment);
        $firstMessage = $experiment->messages()
            ->where('turn', 1)
            ->where('role', AgentRole::Sender)
            ->firstOrFail();
        $firstEvent = $experiment->vocabularyEvents()->orderBy('id')->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('experiments.updates', [
            'experiment' => $experiment,
            'after_message_id' => $firstMessage->id,
            'after_event_id' => $firstEvent->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'messages')
            ->assertJsonCount(3, 'events')
            ->assertJsonCount(2, 'vocabulary')
            ->assertJsonPath('messages.0.role', 'receiver')
            ->assertJsonPath('messages.0.vocabulary.0.symbol', 'signal-1')
            ->assertJsonPath('vocabulary.0.symbol', 'signal-1')
            ->assertJsonPath('vocabulary.0.current.version', 2)
            ->assertJsonPath('cursor.message_id', $experiment->messages()->max('id'))
            ->assertJsonPath('cursor.event_id', $experiment->vocabularyEvents()->max('id'));
    }

    public function test_current_cursor_returns_empty_incremental_collections(): void
    {
        $user = User::factory()->create();
        $experiment = Experiment::factory()->for($user)->create(['max_turns' => 1]);
        app(ExperimentOrchestrator::class)->run($experiment);
        $messageId = (int) $experiment->messages()->max('id');
        $eventId = (int) $experiment->vocabularyEvents()->max('id');

        $response = $this->actingAs($user)->getJson(route('experiments.updates', [
            'experiment' => $experiment,
            'after_message_id' => $messageId,
            'after_event_id' => $eventId,
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertJsonCount(0, 'events')
            ->assertJsonPath('cursor.message_id', $messageId)
            ->assertJsonPath('cursor.event_id', $eventId);
    }

    public function test_unauthenticated_poll_returns_401(): void
    {
        $experiment = Experiment::factory()->create();

        $response = $this->getJson(route('experiments.updates', $experiment));

        $response->assertUnauthorized();
    }

    public function test_polling_another_users_experiment_returns_404(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $experiment = Experiment::factory()->for($owner)->create();

        $response = $this->actingAs($otherUser)
            ->getJson(route('experiments.updates', $experiment));

        $response->assertNotFound();
    }

    public function test_polling_missing_experiment_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('experiments.updates', 999999));

        $response->assertNotFound();
    }
}
