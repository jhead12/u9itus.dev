<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A campaign's opt-in to a marketing channel. Polymorphic on the campaign so
 * political_campaigns and citizen_campaigns (and any future seller tier) share
 * one enablement table. Per-campaign channel overrides ride in `config`.
 */
class CampaignChannel extends Model
{
    protected $table = 'campaign_channels';

    protected $fillable = [
        'campaign_type',
        'campaign_id',
        'marketing_channel_id',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config'     => 'array',
    ];

    public function campaign(): MorphTo
    {
        return $this->morphTo();
    }

    public function marketingChannel(): BelongsTo
    {
        return $this->belongsTo(MarketingChannel::class, 'marketing_channel_id');
    }
}