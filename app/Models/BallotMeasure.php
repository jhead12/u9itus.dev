<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
