<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BallotMeasure extends Model
{
    /** Which body put the measure on the ballot. */
    public const LEVELS = [
        'state' => 'Statewide',
        'county' => 'County',
        'city' => 'City / town',
        'district' => 'School / special district',
    ];

    protected $fillable = [
        'state',
        'level',
        'county',
        'locality',
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

    protected static function booted(): void
    {
        // The unique index keys on this, since county/locality are NULL for statewide rows.
        static::saving(function (self $measure) {
            $measure->place_key = self::placeKeyFor($measure->level, $measure->county, $measure->locality);
        });
    }

    public static function placeKeyFor(?string $level, ?string $county, ?string $locality): string
    {
        return mb_substr(mb_strtolower(trim(($level ?: 'state').'|'.trim((string) $county).'|'.trim((string) $locality))), 0, 255);
    }

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
        ];
    }

    /** "CA", "CA · Alameda County" or "CA · San Diego" — the most specific place the measure covers. */
    public function placeLabel(): string
    {
        $place = $this->level === 'state' ? null : ($this->locality ?: $this->county);

        return strtoupper((string) $this->state).($place ? ' · '.$place : '');
    }

    /** Campaign committees linked to this measure, in any review state. */
    public function committees(): HasMany
    {
        return $this->hasMany(BallotMeasureCommittee::class);
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
