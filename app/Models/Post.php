<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A native blog post authored by a Citizen or Politician.
 *
 * Posts can be geo-tagged so they appear as pins on the U9itus map,
 * promoted with wallet credits, and tagged with existing political topics.
 */
class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'uuid',
        'slug',
        'author_type',
        'author_id',
        'title',
        'subtitle',
        'excerpt',
        'body',
        'featured_image_url',
        'status',
        'published_at',
        'archived_at',
        'allow_comments',
        'is_promoted',
        'promoted_until',
        'credit_spent',
        'location_name',
        'latitude',
        'longitude',
        'state',
        'city',
        'zip',
        'meta_title',
        'meta_description',
        'canonical_url',
    ];

    protected function casts(): array
    {
        return [
            'status'         => PostStatus::class,
            'published_at'   => 'datetime',
            'archived_at'    => 'datetime',
            'promoted_until' => 'datetime',
            'allow_comments' => 'boolean',
            'is_promoted'    => 'boolean',
            'credit_spent'   => 'decimal:2',
            'latitude'       => 'decimal:8',
            'longitude'      => 'decimal:8',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Post $post): void {
            if (empty($post->uuid)) {
                $post->uuid = (string) Str::uuid();
            }
            if (empty($post->slug)) {
                $post->slug = static::generateSlug($post);
            }
        });

        static::updating(function (Post $post): void {
            if ($post->isDirty('title') && ! $post->isDirty('slug')) {
                $post->slug = static::generateSlug($post);
            }
        });
    }

    public static function generateSlug(Post $post): string
    {
        $base = Str::slug($post->title);
        $slug = $base;
        $counter = 1;

        $exists = fn (string $s) => static::where('slug', $s)
            ->when($post->id, fn ($q) => $q->where('id', '!=', $post->id))
            ->exists();

        while ($exists($slug)) {
            $slug = "{$base}-" . ++$counter;
        }

        return $slug;
    }

    /**
     * Sanitize the post body using the same HTML whitelist as campaign blurbs.
     */
    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = $this->sanitizeBody($value);
    }

    private function sanitizeBody($value): ?string
    {
        $html = trim((string) ($value ?? ''));
        if ($html === '') {
            return null;
        }

        $html = preg_replace('/<(script|style|iframe|object|embed)[^>]*>.*?<\/\1>/is', '', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><blockquote><img>');

        $html = preg_replace('/<(\/?)\s*(p|br|strong|b|em|i|u|ul|ol|li|blockquote)\b[^>]*>/i', '<$1$2>', $html) ?? '';

        $html = preg_replace_callback('/<a\b[^>]*>/i', function (array $match): string {
            $tag = $match[0];
            if (! preg_match('/href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $hrefMatch)) {
                return '<a>';
            }
            $href = trim((string) ($hrefMatch[2] ?? $hrefMatch[3] ?? $hrefMatch[4] ?? ''));
            if (! preg_match('/^https?:\/\//i', $href)) {
                return '<a>';
            }
            return '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer nofollow">';
        }, $html) ?? '';

        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            $tag = $match[0];
            if (! preg_match('/src\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $srcMatch)) {
                return '';
            }
            $src = trim((string) ($srcMatch[2] ?? $srcMatch[3] ?? $srcMatch[4] ?? ''));
            if (! preg_match('/^https?:\/\//i', $src)) {
                return '';
            }
            $alt = '';
            if (preg_match('/alt\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $altMatch)) {
                $alt = trim((string) ($altMatch[2] ?? $altMatch[3] ?? $altMatch[4] ?? ''));
            }
            return '<img src="' . e($src) . '" alt="' . e($alt ?: 'Post image') . '" loading="lazy" decoding="async" referrerpolicy="no-referrer">';
        }, $html) ?? '';

        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = trim($html);

        return $html !== '' ? $html : null;
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function topics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            PoliticianTopic::class,
            'post_topic',
            'post_id',
            'topic_id'
        )->withTimestamps();
    }

    public function scopePublished($query): void
    {
        $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopePromoted($query): void
    {
        $query->where('is_promoted', true)
            ->where(function ($q): void {
                $q->whereNull('promoted_until')
                  ->orWhere('promoted_until', '>', now());
            });
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
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function isPromoted(): bool
    {
        return $this->is_promoted
            && ($this->promoted_until === null || $this->promoted_until->gt(now()));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
