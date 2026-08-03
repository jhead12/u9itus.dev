<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Support\PostAlignmentAttributeSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

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
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'promoted_until' => 'datetime',
            'allow_comments' => 'boolean',
            'is_promoted' => 'boolean',
            'credit_spent' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
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
            $slug = "{$base}-".++$counter;
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

        $html = trim(static::htmlSanitizer()->sanitize($html));
        if ($html === '') {
            return null;
        }

        // The sanitizer forces rel/loading/etc. on tags it emits, but only fills in
        // an attribute if the source already had one; give bare <img> a fallback alt.
        $html = preg_replace('/<img(?![^>]*\balt=)([^>]*)>/i', '<img alt="Post image"$1>', $html) ?? $html;

        return $html;
    }

    /**
     * Parser-based sanitizer for post bodies: allows a small set of formatting
     * tags plus https(s) links/images, stripping everything else (scripts,
     * event handlers, style/CSS injection, disallowed schemes).
     */
    private static function htmlSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src', 'alt'])
            // Quill emits text-alignment as a class on block elements (e.g. ql-align-center);
            // the attribute sanitizer below restricts it to that fixed, enumerable set.
            ->allowAttribute('class', ['p', 'li'])
            ->withAttributeSanitizer(new PostAlignmentAttributeSanitizer)
            ->allowLinkSchemes(['https', 'http'])
            ->allowMediaSchemes(['https', 'http'])
            ->forceHttpsUrls()
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('img', 'loading', 'lazy')
            ->forceAttribute('img', 'decoding', 'async')
            ->forceAttribute('img', 'referrerpolicy', 'no-referrer');

        return new HtmlSanitizer($config);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function topics(): BelongsToMany
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
