<?php

namespace App\Models;

use App\Enums\AgentRole;
use Database\Factories\VocabularyRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $vocabulary_entry_id
 * @property int|null $message_id
 * @property int $version
 * @property string $meaning
 * @property string|null $description
 * @property AgentRole $proposer_role
 * @property int $proposed_in_turn
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'version',
    'meaning',
    'description',
    'proposer_role',
    'proposed_in_turn',
])]
class VocabularyRevision extends Model
{
    /** @use HasFactory<VocabularyRevisionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Vocabulary revisions are immutable.'));
        static::deleting(fn () => throw new LogicException('Vocabulary revisions are immutable.'));
    }

    /** @return BelongsTo<VocabularyEntry, $this> */
    public function vocabularyEntry(): BelongsTo
    {
        return $this->belongsTo(VocabularyEntry::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /** @return BelongsToMany<Message, $this> */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class);
    }

    protected function casts(): array
    {
        return [
            'proposer_role' => AgentRole::class,
            'version' => 'integer',
            'proposed_in_turn' => 'integer',
        ];
    }
}
