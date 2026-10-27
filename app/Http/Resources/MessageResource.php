<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
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
            'turn' => $this->turn,
            'role' => $this->role->value,
            'symbolic_content' => $this->symbolic_content,
            'decoded_content' => $this->decoded_content,
            'vocabulary' => $this->whenLoaded(
                'vocabularyRevisions',
                fn () => $this->vocabularyRevisions->map(fn ($revision): array => [
                    'symbol' => $revision->vocabularyEntry->symbol,
                    'version' => $revision->version,
                    'meaning' => $revision->meaning,
                ])->values(),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
