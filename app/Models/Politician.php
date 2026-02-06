<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * A politician or local governance official who creates campaigns
 * (video messages / live feeds) to reach voters.
 */
class Politician extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wix_site_id',
        'wix_member_id',
        'uuid',
        'full_name',
        'political_office',
        'governance_level',
        'district',
        'party_affiliation',
        'state',
        'city',
        'website_url',
        'bio',
        'profile_photo_url',
        'verified_official',
        'kyc_status',
        'stripe_customer_id',
        'total_spent',
        'total_campaigns',
        'total_views_received',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
            'verified_official' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($politician) {
            if (empty($politician->uuid)) {
                $politician->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wixSite()
    {
        return $this->belongsTo(WixSite::class);
    }

    public function campaigns()
    {
        return $this->hasMany(PoliticalCampaign::class);
    }

    /**
     * Voters referred/procured by this politician's campaigns.
     */
    public function procuredVoters()
    {
        return $this->hasManyThrough(ViewSession::class, PoliticalCampaign::class);
    }
}
