<?php

namespace App\Services;

use App\AI\DTO\VocabularyProposal;
use App\Enums\AgentRole;
use App\Enums\VocabularyEventType;
use App\Models\Experiment;
use App\Models\Message;
use App\Models\VocabularyEntry;
use App\Models\VocabularyRevision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VocabularyService
{
    /**
     * @param  list<VocabularyProposal>  $proposals
     * @return Collection<int, VocabularyRevision>
     */
    public function applyProposals(
        Experiment $experiment,
        Message $message,
        array $proposals,
        AgentRole $role,
        int $turn,
    ): Collection {
        return DB::transaction(function () use ($experiment, $message, $proposals, $role, $turn): Collection {
            $revisions = collect();

            foreach ($proposals as $proposal) {
                $entry = $this->lockedEntry($experiment, $proposal->symbol);
                $currentRevision = $entry->revisions()
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                if (
                    $currentRevision !== null
                    && $currentRevision->meaning === $proposal->meaning
                    && $currentRevision->description === $proposal->description
                ) {
                    $revisions->push($currentRevision);

                    continue;
                }

                $revision = $entry->revisions()->make([
                    'version' => $currentRevision === null
                        ? 1
                        : $currentRevision->version + 1,
                    'meaning' => $proposal->meaning,
                    'description' => $proposal->description,
                    'proposer_role' => $role,
                    'proposed_in_turn' => $turn,
                ]);
                $revision->message()->associate($message);
                $revision->save();

                $event = $entry->events()->make([
                    'type' => $currentRevision === null
                        ? VocabularyEventType::Created
                        : VocabularyEventType::Revised,
                    'proposer_role' => $role,
                    'turn' => $turn,
                    'occurred_at' => now(),
                ]);
                $event->experiment()->associate($experiment);
                $event->vocabularyRevision()->associate($revision);
                $event->message()->associate($message);
                $event->save();

                $revisions->push($revision);
            }

            return $revisions;
        }, 3);
    }

    /**
     * @return list<array{symbol: string, meaning: string, description: string|null, version: int}>
     */
    public function activeVocabulary(Experiment $experiment): array
    {
        return array_values($experiment->vocabularyEntries()
            ->with('currentRevision')
            ->orderBy('symbol')
            ->get()
            ->filter(fn (VocabularyEntry $entry): bool => $entry->currentRevision !== null)
            ->map(fn (VocabularyEntry $entry): array => [
                'symbol' => $entry->symbol,
                'meaning' => $entry->currentRevision->meaning,
                'description' => $entry->currentRevision->description,
                'version' => $entry->currentRevision->version,
            ])
            ->values()
            ->all());
    }

    private function lockedEntry(Experiment $experiment, string $symbol): VocabularyEntry
    {
        $entry = $experiment->vocabularyEntries()
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if ($entry !== null) {
            return $entry;
        }

        try {
            return $experiment->vocabularyEntries()->create(['symbol' => $symbol]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $experiment->vocabularyEntries()
                ->where('symbol', $symbol)
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }
}
