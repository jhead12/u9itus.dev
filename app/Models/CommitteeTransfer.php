<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Money a linked committee gave another committee, with the measure its filing named.
 * See the committee_transfers migration.
 */
class CommitteeTransfer extends Model
{
    protected $fillable = [
        'state',
        'from_committee_id',
        'to_committee_id',
        'to_committee_name',
        'measure_reference',
        'measure_number',
        'measure_jurisdiction',
        'position',
        'amount',
        'latest_on',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'latest_on' => 'date',
            'dismissed_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }
}
