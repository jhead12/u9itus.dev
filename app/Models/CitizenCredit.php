<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class CitizenCredit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'citizen_id',
        'transaction_type',
        'amount',
        'balance_after',
        'citizen_campaign_id',
        'related_transaction_id',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (CitizenCredit $credit) {
            if (empty($credit->uuid)) {
                $credit->uuid = (string) Str::uuid();
            }
            if (empty($credit->created_at)) {
                $credit->created_at = now();
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
