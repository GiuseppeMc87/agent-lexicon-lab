<?php

namespace App\Models;

use App\Enums\ExperimentStatus;
use Database\Factories\ExperimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property ExperimentStatus $status
 * @property string $provider
 * @property string $model
 * @property string|null $sender_instructions
 * @property string|null $receiver_instructions
 * @property int $max_turns
 * @property int $current_turn
 * @property int $lock_version
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'status',
    'provider',
    'model',
    'sender_instructions',
    'receiver_instructions',
    'max_turns',
    'current_turn',
    'lock_version',
    'queued_at',
    'started_at',
    'completed_at',
    'failed_at',
    'failure_code',
    'failure_message',
])]
class Experiment extends Model
{
    /** @use HasFactory<ExperimentFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<VocabularyEntry, $this> */
    public function vocabularyEntries(): HasMany
    {
        return $this->hasMany(VocabularyEntry::class);
    }

    /** @return HasMany<VocabularyEvent, $this> */
    public function vocabularyEvents(): HasMany
    {
        return $this->hasMany(VocabularyEvent::class);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $bindingQuery = parent::resolveRouteBindingQuery($query, $value, $field);

        return Auth::check()
            ? $bindingQuery->where('user_id', Auth::id())
            : $bindingQuery;
    }

    protected function casts(): array
    {
        return [
            'status' => ExperimentStatus::class,
            'max_turns' => 'integer',
            'current_turn' => 'integer',
            'lock_version' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
