<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_code',
        'referrer_voter_id',
        'referrer_politician_id',
        'session_id',
        'landing_path',
        'first_seen_at',
        'last_seen_at',
        'converted_user_id',
        'converted_user_type',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }
}
