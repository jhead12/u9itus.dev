<?php

namespace App\Models;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A registered marketing channel / plugin (first-party or third-party).
 *
 * The channel's actual sending logic lives in the provider_class
 * implementation (App\Contracts\MarketingChannel). This row is the
 * registry metadata: slug, label, type, status, and non-secret config.
 */
class MarketingChannel extends Model
{
    protected $table = 'marketing_channels';

    protected $fillable = [
        'uuid',
        'key',
        'label',
        'channel_type',
        'provider_class',
        'is_first_party',
        'status',
        'config',
        'description',
    ];

    protected $casts = [
        'channel_type'   => ChannelType::class,
        'status'         => ChannelStatus::class,
        'is_first_party' => 'boolean',
        'config'         => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (MarketingChannel $channel): void {
            if (empty($channel->uuid)) {
                $channel->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaignChannels(): HasMany
    {
        return $this->hasMany(CampaignChannel::class, 'marketing_channel_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(CampaignDispatch::class, 'marketing_channel_id');
    }

    public function scopeActive($query): void
    {
        $query->where('status', ChannelStatus::Active);
    }
}