<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What the last finance import found for a linked filer ID: whether it exists in the
 * state's data, and the name it files under.
 */
class CommitteeFiler extends Model
{
    protected $fillable = [
        'state',
        'committee_id',
        'source',
        'found',
        'filer_name',
        'latest_filing_on',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'found' => 'boolean',
            'latest_filing_on' => 'date',
            'checked_at' => 'datetime',
        ];
    }

    public static function for(string $state, string $committeeId): ?self
    {
        return self::query()->where('state', strtoupper($state))->where('committee_id', $committeeId)->first();
    }
}
