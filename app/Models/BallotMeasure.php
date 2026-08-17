<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BallotMeasure extends Model
{
    protected $fillable = [
        'state',
        'county',
        'measure_number',
        'title',
        'summary',
        'yes_meaning',
        'no_meaning',
        'election_date',
        'status',
        'source',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
        ];
    }

    /**
     * Voters who have favorited this measure. Inverse of
     * Voter::favoriteBallotMeasures(). Used for withCount/withExists
     * on the directory + show pages.
     */
    public function favoriteVoters(): BelongsToMany
    {
        return $this->belongsToMany(
            Voter::class,
            'voter_favorite_ballot_measures',
            'ballot_measure_id',
            'voter_id'
        )->withPivot('favorited_at');
    }
}
