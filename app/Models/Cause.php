<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A specific, nameable issue under a Topic (e.g. "Expand Medicaid in Texas"
 * under the "Healthcare" topic) that a voter can favorite.
 */
class Cause extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'title',
        'description',
        'state',
        'county',
        'status',
        'source_url',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(PoliticianTopic::class, 'topic_id');
    }
}
