<?php

namespace App\Models;

use App\Enums\AgentRole;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $experiment_id
 * @property int $turn
 * @property AgentRole $role
 * @property string $symbolic_content
 * @property string $decoded_content
 * @property string|null $provider_request_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'turn',
    'role',
    'symbolic_content',
    'decoded_content',
    'provider_request_id',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /** @return BelongsTo<Experiment, $this> */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    /** @return BelongsToMany<VocabularyRevision, $this> */
    public function vocabularyRevisions(): BelongsToMany
    {
        return $this->belongsToMany(VocabularyRevision::class);
    }

    /** @return HasMany<VocabularyRevision, $this> */
    public function proposedRevisions(): HasMany
    {
        return $this->hasMany(VocabularyRevision::class);
    }

    protected function casts(): array
    {
        return [
            'role' => AgentRole::class,
            'turn' => 'integer',
        ];
    }
}
