<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CongressCommitteeAssignment extends Model
{
    protected $fillable = ['bioguide_id', 'committee_code', 'parent_code', 'name', 'chamber', 'title', 'rank', 'side', 'url'];

    protected function casts(): array
    {
        return ['rank' => 'integer'];
    }
}
