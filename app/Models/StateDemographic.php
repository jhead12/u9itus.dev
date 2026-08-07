<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateDemographic extends Model
{
    protected $fillable = [
        'state',
        'poverty_rate',
        'census_year',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'poverty_rate' => 'decimal:2',
            'census_year' => 'integer',
        ];
    }
}
