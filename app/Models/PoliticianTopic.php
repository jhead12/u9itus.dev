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
        // Matching vocabulary for news, bill titles and Congress.gov policy areas
        'keywords',
        'policy_areas',
        // An ongoing issue or a current event, and what "for" and "against" mean for it
        'kind',
        'support_label',
        'oppose_label',
    ];

    public const KIND_ISSUE = 'issue';

    public const KIND_CURRENT_EVENT = 'current_event';

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'sort_order'       => 'integer',
            'voter_selectable' => 'boolean',
            'auto_earned_only' => 'boolean',
            'keywords'         => 'array',
            'policy_areas'     => 'array',
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
     * Only topics with both labels can show a position: "Opposes Education" is not one.
     */
    public function hasStanceLabels(): bool
    {
        return filled($this->support_label) && filled($this->oppose_label);
    }

    public function stanceLabel(?string $stance): ?string
    {
        return match ($stance) {
            'support' => $this->support_label,
            'oppose' => $this->oppose_label,
            default => null,
        };
    }

    /**
     * Get active topics for display.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
