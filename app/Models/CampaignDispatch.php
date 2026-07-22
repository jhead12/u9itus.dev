<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * One row per (campaign, channel, recipient) dispatch attempt — the
 * provenance + audit log for the entire marketing system. See the migration
 * docblock for how this table feeds cost reconciliation, disbursement
 * reporting, and the future geofenced-QR proof-of-delivery.
 */
class CampaignDispatch extends Model
{
    protected $table = 'campaign_dispatches';

    protected $fillable = [
        'uuid',
        'campaign_type',
        'campaign_id',
        'marketing_channel_id',
        'voter_id',
        'channel_type',
        'status',
        'provider_message_id',
        'payload',
        'cost_cents',
        'error_message',
        'dispatched_at',
        'delivered_at',
        'bounced_at',
    ];

    protected $casts = [
        'status'    => DispatchStatus::class,
        'payload'   => 'json',
        'cost_cents'=> 'integer',
        'dispatched_at' => 'datetime',
        'delivered_at'  => 'datetime',
        'bounced_at'    => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (CampaignDispatch $dispatch): void {
            if (empty($dispatch->uuid)) {
                $dispatch->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): MorphTo
    {
        return $this->morphTo();
    }

    public function marketingChannel(): BelongsTo
    {
        return $this->belongsTo(MarketingChannel::class, 'marketing_channel_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class, 'voter_id');
    }

    /** Rows that still need a handoff to the channel. */
    public function scopeQueued($query): void
    {
        $query->where('status', DispatchStatus::Queued);
    }
}