<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A person an authoritative source (FEC) lists for a cycle — see the create_candidate_roster_table migration. */
class CandidateRoster extends Model
{
    protected $table = 'candidate_roster';

    protected $fillable = [
        'source', 'source_id', 'full_name', 'identity_key', 'state', 'office', 'district', 'election_year', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'election_year' => 'integer'];
    }
}
