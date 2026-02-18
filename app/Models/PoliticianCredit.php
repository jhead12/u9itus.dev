<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class PoliticianCredit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'politician_id',
        'transaction_type',
        'amount',
        'balance_after',
        'campaign_id',
        'related_transaction_id',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (PoliticianCredit $credit) {
            if (empty($credit->uuid)) {
                $credit->uuid = (string) Str::uuid();
            }
            if (empty($credit->created_at)) {
                $credit->created_at = now();
            }
        });
    }
}
