<?php

namespace App\Models;

use Database\Factories\VocabularyEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $experiment_id
 * @property string $symbol
 * @property-read VocabularyRevision|null $currentRevision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['symbol'])]
class VocabularyEntry extends Model
{
    /** @use HasFactory<VocabularyEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<Experiment, $this> */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    /** @return HasMany<VocabularyRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(VocabularyRevision::class);
    }

    /** @return HasOne<VocabularyRevision, $this> */
    public function currentRevision(): HasOne
    {
        return $this->hasOne(VocabularyRevision::class)->ofMany('version', 'max');
    }

    /** @return HasMany<VocabularyEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(VocabularyEvent::class);
    }
}
