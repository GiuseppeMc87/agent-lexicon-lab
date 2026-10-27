<?php

namespace App\Http\Resources;

use App\Models\VocabularyEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VocabularyEvent */
class VocabularyEventResource extends JsonResource
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
            'type' => $this->type->value,
            'symbol' => $this->whenLoaded('vocabularyEntry', fn () => $this->vocabularyEntry->symbol),
            'version' => $this->whenLoaded('vocabularyRevision', fn () => $this->vocabularyRevision->version),
            'meaning' => $this->whenLoaded('vocabularyRevision', fn () => $this->vocabularyRevision->meaning),
            'description' => $this->whenLoaded('vocabularyRevision', fn () => $this->vocabularyRevision->description),
            'proposer_role' => $this->proposer_role->value,
            'turn' => $this->turn,
            'message_id' => $this->message_id,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
