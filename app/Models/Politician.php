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

    protected $table = 'politicians';

    protected $fillable = [
        'user_id',
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
        'credit_balance',
        'total_spent',
        'total_campaigns',
        'total_views_received',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_balance' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'verified_official' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Politician $politician): void {
            if (empty($politician->uuid)) {
                $politician->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Re-derive credit_balance from the ledger and persist it.
     * Call this after any credit transaction to keep the denormalized
     * column in sync.
     */
    public function syncCreditBalance(): void
    {
        $latest = $this->credits()->latest('created_at')->first();
        $this->credit_balance = $latest ? $latest->balance_after : 0;
        $this->saveQuietly();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticalCampaign::class);
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PoliticianCredit::class);
    }

    /**
     * View sessions across all of this politician's campaigns.
     */
    public function viewSessions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ViewSession::class, PoliticalCampaign::class);
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
