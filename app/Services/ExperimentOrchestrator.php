<?php

namespace App\Services;

use App\AI\Contracts\AgentProvider;
use App\AI\DTO\AgentContext;
use App\AI\DTO\ReceiverResponse;
use App\AI\DTO\SenderResponse;
use App\AI\Exceptions\ProviderException;
use App\Enums\AgentRole;
use App\Enums\ExperimentStatus;
use App\Models\Experiment;
use App\Models\Message;
use App\Models\VocabularyEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ExperimentOrchestrator
{
    public function __construct(
        private readonly AgentProvider $provider,
        private readonly VocabularyService $vocabulary,
        private readonly TokenDecoder $decoder,
    ) {}

    public function run(Experiment $experiment): void
    {
        if (! $this->begin($experiment)) {
            return;
        }

        try {
            $experiment->refresh();
            $this->assertProviderMatches($experiment);

            while ($experiment->current_turn < $experiment->max_turns) {
                $turn = $experiment->current_turn + 1;
                $messages = $this->messageHistory($experiment);
                $visibleVocabulary = $this->vocabulary->activeVocabulary($experiment);

                $senderResponse = $this->provider->generateSenderResponse(
                    new AgentContext(
                        experimentName: $experiment->name,
                        turn: $turn,
                        maximumTurns: $experiment->max_turns,
                        messages: $messages,
                        vocabulary: $visibleVocabulary,
                    ),
                );

                $receiverResponse = $this->provider->generateReceiverResponse(
                    new AgentContext(
                        experimentName: $experiment->name,
                        turn: $turn,
                        maximumTurns: $experiment->max_turns,
                        messages: [
                            ...$messages,
                            [
                                'turn' => $turn,
                                'role' => AgentRole::Sender->value,
                                'message' => $senderResponse->message,
                            ],
                        ],
                        vocabulary: $visibleVocabulary,
                    ),
                );

                $this->persistTurn($experiment, $turn, $senderResponse, $receiverResponse);
                $experiment->refresh();
            }

            $this->complete($experiment);
        } catch (Throwable $exception) {
            $this->fail($experiment, $exception);

            throw $exception;
        }
    }

    private function begin(Experiment $experiment): bool
    {
        return DB::transaction(function () use ($experiment): bool {
            $locked = Experiment::query()->lockForUpdate()->findOrFail($experiment->id);

            if ($locked->status === ExperimentStatus::Completed) {
                return false;
            }

            if (! in_array($locked->status, [ExperimentStatus::Pending, ExperimentStatus::Running], true)) {
                throw new RuntimeException('The experiment is not available to run.');
            }

            if ($locked->status === ExperimentStatus::Pending) {
                $locked->update([
                    'status' => ExperimentStatus::Running,
                    'started_at' => $locked->started_at ?? now(),
                    'failed_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                    'lock_version' => $locked->lock_version + 1,
                ]);
            }

            return true;
        });
    }

    private function assertProviderMatches(Experiment $experiment): void
    {
        if ($experiment->provider !== $this->provider->name()) {
            throw new ProviderException(
                'provider_configuration_changed',
                'The configured AI provider no longer matches this experiment.',
            );
        }
    }

    /**
     * @return list<array{turn: int, role: string, message: string}>
     */
    private function messageHistory(Experiment $experiment): array
    {
        return array_values($experiment->messages()
            ->orderBy('turn')
            ->orderByRaw("CASE role WHEN 'sender' THEN 0 ELSE 1 END")
            ->get(['turn', 'role', 'symbolic_content'])
            ->map(fn (Message $message): array => [
                'turn' => $message->turn,
                'role' => $message->role->value,
                'message' => $message->symbolic_content,
            ])
            ->values()
            ->all());
    }

    private function persistTurn(
        Experiment $experiment,
        int $turn,
        SenderResponse $senderResponse,
        ReceiverResponse $receiverResponse,
    ): void {
        DB::transaction(function () use ($experiment, $turn, $senderResponse, $receiverResponse): void {
            $locked = Experiment::query()->lockForUpdate()->findOrFail($experiment->id);

            if ($locked->current_turn >= $turn) {
                return;
            }

            if ($locked->status !== ExperimentStatus::Running || $locked->current_turn !== $turn - 1) {
                throw new RuntimeException('Experiment progress changed during the turn.');
            }

            $senderMessage = $this->createMessage(
                $locked,
                $turn,
                AgentRole::Sender,
                $senderResponse->message,
                $senderResponse->requestId,
            );
            $this->vocabulary->applyProposals(
                $locked,
                $senderMessage,
                $senderResponse->vocabulary,
                AgentRole::Sender,
                $turn,
            );
            $this->decodeAndLink($locked, $senderMessage);

            $receiverMessage = $this->createMessage(
                $locked,
                $turn,
                AgentRole::Receiver,
                $receiverResponse->message,
                $receiverResponse->requestId,
            );
            $this->vocabulary->applyProposals(
                $locked,
                $receiverMessage,
                $receiverResponse->vocabulary,
                AgentRole::Receiver,
                $turn,
            );
            $this->decodeAndLink($locked, $receiverMessage);

            $locked->update([
                'current_turn' => $turn,
                'lock_version' => $locked->lock_version + 1,
            ]);
        }, 3);
    }

    private function createMessage(
        Experiment $experiment,
        int $turn,
        AgentRole $role,
        string $content,
        ?string $requestId,
    ): Message {
        return $experiment->messages()->create([
            'turn' => $turn,
            'role' => $role,
            'symbolic_content' => $content,
            'decoded_content' => $content,
            'provider_request_id' => $requestId,
        ]);
    }

    private function decodeAndLink(Experiment $experiment, Message $message): void
    {
        $symbols = $this->decoder->extractSymbols($message->symbolic_content);
        $entries = $experiment->vocabularyEntries()
            ->whereIn('symbol', $symbols)
            ->with('currentRevision')
            ->get();
        $definitions = $entries
            ->filter(fn (VocabularyEntry $entry): bool => $entry->currentRevision !== null)
            ->mapWithKeys(fn (VocabularyEntry $entry): array => [
                $entry->symbol => $entry->currentRevision->meaning,
            ])
            ->all();

        $message->update([
            'decoded_content' => $this->decoder->decode($message->symbolic_content, $definitions),
        ]);
        $message->vocabularyRevisions()->sync(
            $entries->pluck('currentRevision.id')->filter()->values()->all(),
        );
    }

    private function complete(Experiment $experiment): void
    {
        DB::transaction(function () use ($experiment): void {
            $locked = Experiment::query()->lockForUpdate()->findOrFail($experiment->id);

            if ($locked->status !== ExperimentStatus::Running) {
                return;
            }

            $locked->update([
                'status' => ExperimentStatus::Completed,
                'completed_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
        });
    }

    private function fail(Experiment $experiment, Throwable $exception): void
    {
        $failureCode = $exception instanceof ProviderException
            ? $exception->failureCode
            : 'experiment_execution_failed';
        $failureMessage = $exception instanceof ProviderException
            ? $exception->safeMessage
            : 'The experiment stopped because an internal operation failed.';

        Experiment::query()
            ->whereKey($experiment->id)
            ->where('status', '!=', ExperimentStatus::Completed->value)
            ->update([
                'status' => ExperimentStatus::Failed->value,
                'failed_at' => now(),
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'lock_version' => DB::raw('lock_version + 1'),
                'updated_at' => now(),
            ]);
    }
}
