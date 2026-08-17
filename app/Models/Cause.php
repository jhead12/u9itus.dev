<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Voters who have favorited this cause. Inverse of Voter::favoriteCauses().
     * Used for withCount/withExists on the directory + show pages.
     */
    public function favoriteVoters(): BelongsToMany
    {
        return $this->belongsToMany(
            Voter::class,
            'voter_favorite_causes',
            'cause_id',
            'voter_id'
        )->withPivot('favorited_at');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
