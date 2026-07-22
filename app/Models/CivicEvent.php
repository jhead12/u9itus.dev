<?php

namespace App\Models;

use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
use App\Models\PoliticianTopic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A civic event — town hall, ballot-measure drive, rally, workshop, etc.
 * Hosted by a Citizen, Politician, or later a NeighborhoodGroup.
 */
class CivicEvent extends Model
{
    use HasFactory;

    protected $table = 'civic_events';

    protected $fillable = [
        'uuid',
        'slug',
        'host_type',
        'host_id',
        'event_type',
        'status',
        'title',
        'description',
        'location_name',
        'venue_name',
        'address',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'starts_at',
        'ends_at',
        'timezone',
        'capacity',
        'rsvp_requires_approval',
        'is_virtual',
        'virtual_url',
        'image_url',
        'banner_url',
        'goal_amount_cents',
        'group_id',
        'related_post_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CivicEventStatus::class,
            'event_type' => CivicEventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rsvp_requires_approval' => 'boolean',
            'is_virtual' => 'boolean',
            'capacity' => 'integer',
            'goal_amount_cents' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CivicEvent $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
            if (empty($event->slug)) {
                $event->slug = static::generateSlug($event);
            }
        });

        static::updating(function (CivicEvent $event): void {
            if ($event->isDirty('title') && ! $event->isDirty('slug')) {
                $event->slug = static::generateSlug($event);
            }
        });
    }

    public static function generateSlug(CivicEvent $event): string
    {
        $base = Str::slug($event->title);
        $slug = $base;
        $counter = 1;

        $exists = fn (string $s) => static::where('slug', $s)
            ->when($event->id, fn ($q) => $q->where('id', '!=', $event->id))
            ->exists();

        while ($exists($slug)) {
            $slug = "{$base}-" . ++$counter;
        }

        return $slug;
    }

    public function host(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function rsvps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function relatedPost(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    public function topics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PoliticianTopic::class, 'civic_event_topic');
    }

    public function scopePublished($query): void
    {
        $query->where('status', CivicEventStatus::Published)
            ->where('starts_at', '>', now());
    }

    public function scopeUpcoming($query): void
    {
        $query->where('starts_at', '>', now())
            ->whereNotIn('status', [CivicEventStatus::Cancelled, CivicEventStatus::Completed]);
    }

    public function scopeGeoTagged($query): void
    {
        $query->whereNotNull('latitude')
            ->whereNotNull('longitude');
    }

    public function scopeWithinBounds($query, float $south, float $west, float $north, float $east): void
    {
        $query->whereBetween('latitude', [$south, $north])
            ->whereBetween('longitude', [$west, $east]);
    }

    public function isPublished(): bool
    {
        return $this->status === CivicEventStatus::Published
            && $this->starts_at->gt(now());
    }

    public function isFull(): bool
    {
        if (! $this->capacity) {
            return false;
        }

        $attending = $this->rsvps()
            ->whereIn('status', ['yes', 'approved'])
            ->sum('guest_count');

        return $attending >= $this->capacity;
    }

    public function attendingCount(): int
    {
        return (int) $this->rsvps()
            ->whereIn('status', ['yes', 'approved'])
            ->sum('guest_count');
    }

    public function waitlistCount(): int
    {
        return (int) $this->rsvps()
            ->where('status', 'waitlist')
            ->sum('guest_count');
    }
}
