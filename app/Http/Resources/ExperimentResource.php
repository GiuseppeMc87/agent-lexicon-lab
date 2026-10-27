<?php

namespace App\Http\Resources;

use App\Models\Experiment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Experiment */
class ExperimentResource extends JsonResource
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
            'name' => $this->name,
            'status' => $this->status->value,
            'provider' => $this->provider,
            'model' => $this->model,
            'sender_instructions' => $this->sender_instructions,
            'receiver_instructions' => $this->receiver_instructions,
            'max_turns' => $this->max_turns,
            'current_turn' => $this->current_turn,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'messages_count' => $this->whenCounted('messages'),
            'vocabulary_entries_count' => $this->whenCounted('vocabularyEntries'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
