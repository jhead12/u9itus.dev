<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One contributor's giving to a committee in a calendar year. See the
 * committee_donors migration.
 */
class CommitteeDonor extends Model
{
    protected $fillable = [
        'state',
        'committee_id',
        'year',
        'donor_name',
        'entity_type',
        'donor_committee_id',
        'employer',
        'amount',
        'nonmonetary',
        'late',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'float',
            'nonmonetary' => 'float',
            'late' => 'float',
        ];
    }

    public function isCommittee(): bool
    {
        return $this->donor_committee_id !== null;
    }
}
