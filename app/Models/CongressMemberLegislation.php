<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CongressMemberLegislation extends Model
{
    protected $table = 'congress_member_legislation';

    protected $fillable = ['bioguide_id', 'since_congress', 'sponsored_total', 'cosponsored_total', 'policy_areas', 'topics'];

    protected function casts(): array
    {
        return [
            'since_congress' => 'integer',
            'sponsored_total' => 'integer',
            'cosponsored_total' => 'integer',
            'policy_areas' => 'array',
            'topics' => 'array',
        ];
    }
}
