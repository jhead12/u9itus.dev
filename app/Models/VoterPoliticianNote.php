<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voter's personal, single running note about a politician. One row per
 * voter+politician pair — enforced by a unique constraint, not a journal.
 */
class VoterPoliticianNote extends Model
{
    protected $fillable = [
        'voter_id',
        'politician_id',
        'body',
    ];

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }
}
