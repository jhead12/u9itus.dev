<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityDemographic extends Model
{
    protected $fillable = [
        'state',
        'city_name',
        'population',
        'poverty_rate',
        'pct_bachelors_or_higher',
        'median_household_income',
        'census_year',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'poverty_rate' => 'decimal:2',
            'pct_bachelors_or_higher' => 'decimal:2',
            'median_household_income' => 'integer',
            'census_year' => 'integer',
        ];
    }
}
