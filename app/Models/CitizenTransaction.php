<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CitizenTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'citizen_campaign_id',
        'citizen_id',
        'transaction_type',
        'amount',
        'currency',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_refund_id',
        'status',
        'receipt_sent_at',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'receipt_sent_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (CitizenTransaction $tx) {
            if (empty($tx->uuid)) {
                $tx->uuid = (string) Str::uuid();
            }
        });
    }

    public function citizen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Citizen::class);
    }

    public function campaign(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CitizenCampaign::class, 'citizen_campaign_id');
    }
}
