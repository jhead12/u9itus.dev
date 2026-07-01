<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A curated topic/category for campaign discovery and tagging.
 * Examples: Healthcare, Climate Action, Education, Jobs, Housing
 */
class PoliticianTopic extends Model
{
    use HasFactory;

    protected $table = 'politician_topics';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
        // Phase 19 — badge catalog fields
        'badge_icon_url',
        'badge_color',
        'voter_selectable',
        'auto_earned_only',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
            'voter_selectable' => 'boolean',
            'auto_earned_only' => 'boolean',
        ];
    }

    /**
     * Campaigns tagged with this topic.
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(
            PoliticalCampaign::class,
            'campaign_topic',
            'topic_id',
            'campaign_id'
        )->withTimestamps();
    }

    /**
     * Get active topics for display.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
