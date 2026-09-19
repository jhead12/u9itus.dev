<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongressMemberVote extends Model
{
    public $timestamps = false;

    protected $fillable = ['congress_vote_id', 'bioguide_id', 'party', 'vote'];

    public function congressVote(): BelongsTo
    {
        return $this->belongsTo(CongressVote::class);
    }
}
