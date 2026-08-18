<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hand-maintained reference count of declared candidates per gubernatorial
 * race, sourced by eyeballing the race's Ballotpedia page (see source_url) —
 * not scraped. Used by politicians:audit-race-counts as a cross-check
 * against our own Politician rows, since the number itself is part of what
 * Ballotpedia sells and a small, slow-changing set (~36 races/cycle) doesn't
 * justify scraping infrastructure.
 */
class GovernorRaceCandidateCount extends Model
{
    protected $fillable = [
        'state',
        'election_year',
        'expected_count',
        'source',
        'source_url',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'election_year' => 'integer',
            'expected_count' => 'integer',
            'verified_at' => 'date',
        ];
    }
}
