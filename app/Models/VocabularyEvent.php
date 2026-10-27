<?php

namespace App\Models;

use App\Enums\AgentRole;
use App\Enums\VocabularyEventType;
use Database\Factories\VocabularyEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $experiment_id
 * @property int $vocabulary_entry_id
 * @property int $vocabulary_revision_id
 * @property int|null $message_id
 * @property VocabularyEventType $type
 * @property AgentRole $proposer_role
 * @property int $turn
 * @property Carbon $occurred_at
 */
#[Fillable(['type', 'proposer_role', 'turn', 'occurred_at'])]
class VocabularyEvent extends Model
{
    /** @use HasFactory<VocabularyEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Vocabulary events are append-only.'));
        static::deleting(fn () => throw new LogicException('Vocabulary events are append-only.'));
    }

    /** @return BelongsTo<Experiment, $this> */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    /** @return BelongsTo<VocabularyEntry, $this> */
    public function vocabularyEntry(): BelongsTo
    {
        return $this->belongsTo(VocabularyEntry::class);
    }

    /** @return BelongsTo<VocabularyRevision, $this> */
    public function vocabularyRevision(): BelongsTo
    {
        return $this->belongsTo(VocabularyRevision::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function casts(): array
    {
        return [
            'type' => VocabularyEventType::class,
            'proposer_role' => AgentRole::class,
            'turn' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
