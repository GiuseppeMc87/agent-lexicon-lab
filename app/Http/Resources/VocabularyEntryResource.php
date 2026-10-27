<?php

namespace App\Http\Resources;

use App\Models\VocabularyEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VocabularyEntry */
class VocabularyEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'current' => $this->whenLoaded('currentRevision', fn () => $this->currentRevision === null
                ? null
                : [
                    'id' => $this->currentRevision->id,
                    'version' => $this->currentRevision->version,
                    'meaning' => $this->currentRevision->meaning,
                    'description' => $this->currentRevision->description,
                    'proposer_role' => $this->currentRevision->proposer_role->value,
                    'turn' => $this->currentRevision->proposed_in_turn,
                ]),
            'revisions' => $this->whenLoaded(
                'revisions',
                fn () => $this->revisions->map(fn ($revision): array => [
                    'id' => $revision->id,
                    'version' => $revision->version,
                    'meaning' => $revision->meaning,
                    'description' => $revision->description,
                    'proposer_role' => $revision->proposer_role->value,
                    'turn' => $revision->proposed_in_turn,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ])->values(),
            ),
        ];
    }
}
