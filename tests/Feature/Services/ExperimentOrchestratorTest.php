<?php

namespace Tests\Feature\Services;

use App\AI\Contracts\AgentProvider;
use App\AI\DTO\AgentContext;
use App\AI\DTO\ReceiverResponse;
use App\AI\DTO\SenderResponse;
use App\AI\DTO\VocabularyProposal;
use App\AI\Exceptions\ProviderException;
use App\Enums\AgentRole;
use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Models\Message;
use App\Models\VocabularyEvent;
use App\Models\VocabularyRevision;
use App\Services\ExperimentOrchestrator;
use App\Services\VocabularyService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class ExperimentOrchestratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fake_provider_completes_lifecycle_and_persists_vocabulary_history(): void
    {
        Http::preventStrayRequests();
        config()->set('agent.driver', 'fake');
        $experiment = Experiment::factory()->create(['max_turns' => 2]);

        app(ExperimentOrchestrator::class)->run($experiment);

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Completed, $experiment->status);
        $this->assertSame(2, $experiment->current_turn);
        $this->assertNotNull($experiment->completed_at);
        $this->assertSame(4, Message::query()->whereBelongsTo($experiment)->count());
        $this->assertSame(2, $experiment->vocabularyEntries()->count());
        $this->assertSame(4, VocabularyRevision::query()->count());
        $this->assertSame(4, VocabularyEvent::query()->count());
        $this->assertSame(4, DB::table('message_vocabulary_revision')->count());

        $sender = $experiment->messages()
            ->where('turn', 1)
            ->where('role', AgentRole::Sender)
            ->firstOrFail();
        $receiver = $experiment->messages()
            ->where('turn', 1)
            ->where('role', AgentRole::Receiver)
            ->firstOrFail();
        $this->assertSame(
            'Sender turn 1 introduces sender meaning for turn 1.',
            $sender->decoded_content,
        );
        $this->assertSame(
            'Receiver turn 1 answers with receiver interpretation for turn 1.',
            $receiver->decoded_content,
        );
        $this->assertSame(1, $sender->vocabularyRevisions()->value('version'));
        $this->assertSame(2, $receiver->vocabularyRevisions()->value('version'));
    }

    public function test_receiver_cannot_see_vocabulary_proposed_by_sender_in_same_turn(): void
    {
        $provider = new class implements AgentProvider
        {
            /** @var array<mixed> */
            public array $receiverVocabulary = [];

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'contract-spy';
            }

            public function generateSenderResponse(AgentContext $context): SenderResponse
            {
                return new SenderResponse('Use [[new-signal]].', [
                    new VocabularyProposal('new-signal', 'sender meaning', null),
                ]);
            }

            public function generateReceiverResponse(AgentContext $context): ReceiverResponse
            {
                $this->receiverVocabulary = $context->vocabulary;

                return new ReceiverResponse('I infer [[new-signal]].', [
                    new VocabularyProposal('new-signal', 'receiver inference', null),
                ]);
            }
        };
        $this->app->instance(AgentProvider::class, $provider);
        $experiment = Experiment::factory()->create([
            'model' => 'contract-spy',
            'max_turns' => 1,
        ]);

        app(ExperimentOrchestrator::class)->run($experiment);

        $this->assertSame([], $provider->receiverVocabulary);
    }

    public function test_each_role_receives_its_own_experiment_instructions(): void
    {
        $provider = new class implements AgentProvider
        {
            public ?string $senderInstructions = null;

            public ?string $receiverInstructions = null;

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'instruction-spy';
            }

            public function generateSenderResponse(AgentContext $context): SenderResponse
            {
                $this->senderInstructions = $context->roleInstructions;

                return new SenderResponse('Use [[signal]].', [
                    new VocabularyProposal('signal', 'sender meaning', null),
                ]);
            }

            public function generateReceiverResponse(AgentContext $context): ReceiverResponse
            {
                $this->receiverInstructions = $context->roleInstructions;

                return new ReceiverResponse('I infer [[signal]].', [
                    new VocabularyProposal('signal', 'receiver meaning', null),
                ]);
            }
        };
        $this->app->instance(AgentProvider::class, $provider);
        $experiment = Experiment::factory()->create([
            'model' => 'instruction-spy',
            'max_turns' => 1,
            'sender_instructions' => 'Introduce spatial concepts.',
            'receiver_instructions' => 'Prefer cautious interpretations.',
        ]);

        app(ExperimentOrchestrator::class)->run($experiment);

        $this->assertSame('Introduce spatial concepts.', $provider->senderInstructions);
        $this->assertSame('Prefer cautious interpretations.', $provider->receiverInstructions);
    }

    public function test_missing_role_instructions_use_configured_defaults(): void
    {
        config()->set('agent.default_instructions.sender', 'Default sender brief.');
        config()->set('agent.default_instructions.receiver', 'Default receiver brief.');
        $provider = new class implements AgentProvider
        {
            public ?string $senderInstructions = null;

            public ?string $receiverInstructions = null;

            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'default-instruction-spy';
            }

            public function generateSenderResponse(AgentContext $context): SenderResponse
            {
                $this->senderInstructions = $context->roleInstructions;

                return new SenderResponse('A sender reply.', []);
            }

            public function generateReceiverResponse(AgentContext $context): ReceiverResponse
            {
                $this->receiverInstructions = $context->roleInstructions;

                return new ReceiverResponse('A receiver reply.', []);
            }
        };
        $this->app->instance(AgentProvider::class, $provider);
        $experiment = Experiment::factory()->create([
            'model' => 'default-instruction-spy',
            'max_turns' => 1,
            'sender_instructions' => null,
            'receiver_instructions' => null,
        ]);

        app(ExperimentOrchestrator::class)->run($experiment);

        $this->assertSame('Default sender brief.', $provider->senderInstructions);
        $this->assertSame('Default receiver brief.', $provider->receiverInstructions);
    }

    public function test_provider_failure_sets_terminal_sanitized_failed_state(): void
    {
        $provider = new class implements AgentProvider
        {
            public function name(): string
            {
                return 'fake';
            }

            public function model(): string
            {
                return 'failing-fake';
            }

            public function generateSenderResponse(AgentContext $context): SenderResponse
            {
                throw new ProviderException(
                    'provider_rate_limited',
                    'The AI provider rejected the request.',
                );
            }

            public function generateReceiverResponse(AgentContext $context): ReceiverResponse
            {
                throw new LogicException('Receiver should not be called.');
            }
        };
        $this->app->instance(AgentProvider::class, $provider);
        $experiment = Experiment::factory()->create([
            'model' => 'failing-fake',
            'max_turns' => 1,
        ]);

        try {
            app(ExperimentOrchestrator::class)->run($experiment);
            $this->fail('The provider failure was swallowed.');
        } catch (ProviderException $exception) {
            $this->assertSame('provider_rate_limited', $exception->failureCode);
        }

        $experiment->refresh();
        $this->assertSame(ExperimentStatus::Failed, $experiment->status);
        $this->assertSame('provider_rate_limited', $experiment->failure_code);
        $this->assertSame(
            'The AI provider rejected the request.',
            $experiment->failure_message,
        );
        $this->assertSame(0, $experiment->messages()->count());
    }

    public function test_revision_locking_and_unique_versions_preserve_order(): void
    {
        $experiment = Experiment::factory()->create();
        $sender = Message::factory()->for($experiment)->create();
        $receiver = Message::factory()->for($experiment)->create([
            'role' => AgentRole::Receiver,
        ]);
        $service = app(VocabularyService::class);

        $service->applyProposals($experiment, $sender, [
            new VocabularyProposal('shared', 'first meaning', null),
        ], AgentRole::Sender, 1);
        $service->applyProposals($experiment, $receiver, [
            new VocabularyProposal('shared', 'second meaning', 'revised'),
        ], AgentRole::Receiver, 1);

        $entry = $experiment->vocabularyEntries()->firstOrFail();
        $this->assertSame([1, 2], $entry->revisions()->orderBy('version')->pluck('version')->all());

        $this->expectException(QueryException::class);
        $entry->revisions()->create([
            'version' => 2,
            'meaning' => 'conflicting concurrent meaning',
            'description' => null,
            'proposer_role' => AgentRole::Sender,
            'proposed_in_turn' => 1,
        ]);
    }

    public function test_revisions_and_events_are_immutable_through_models(): void
    {
        $experiment = Experiment::factory()->create();
        $message = Message::factory()->for($experiment)->create();
        app(VocabularyService::class)->applyProposals($experiment, $message, [
            new VocabularyProposal('stable', 'original meaning', null),
        ], AgentRole::Sender, 1);
        $revision = VocabularyRevision::query()->firstOrFail();
        $event = VocabularyEvent::query()->firstOrFail();

        try {
            $revision->update(['meaning' => 'mutated']);
            $this->fail('An immutable revision was updated.');
        } catch (LogicException $exception) {
            $this->assertSame('Vocabulary revisions are immutable.', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $event->delete();
    }
}
