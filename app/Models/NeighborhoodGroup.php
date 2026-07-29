<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class NeighborhoodGroup extends Model
{
    use HasFactory;

    /** Civic scope a group organizes at — see routes/standalone.php's
     *  groups.public.show for how this becomes a URL segment. */
    public const SCOPES = ['Local', 'District', 'State', 'National'];

    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'description',
        'city',
        'state',
        'zip',
        'scope',
        'admin_user_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (NeighborhoodGroup $group): void {
            if (empty($group->uuid)) {
                $group->uuid = (string) Str::uuid();
            }
            if (empty($group->slug)) {
                $group->slug = static::generateSlug($group);
            }
        });

        // Slug is intentionally stable after creation, unlike Politician's
        // slug (which regenerates on name/city change) — a group's public
        // URL may already be shared/bookmarked by members, so renaming it
        // in Settings must not silently break existing links.
    }

    /**
     * Generate a unique slug: {5-char-uuid-prefix}-{seo-readable-name}
     * e.g. a3f9b-riverside-neighbors
     */
    public static function generateSlug(NeighborhoodGroup $group): string
    {
        $uuid = $group->uuid ?: (string) Str::uuid();
        $prefix = substr($uuid, 0, 5);
        $city = $group->city ?? '';
        $base = Str::slug("{$group->name} {$city}");
        $cand = "{$prefix}-{$base}";

        $counter = 0;
        $exists = fn (string $s) => static::where('slug', $s)
            ->when($group->id, fn ($q) => $q->where('id', '!=', $group->id))
            ->exists();

        while ($exists($cand)) {
            $counter++;
            $cand = "{$prefix}-{$base}-{$counter}";
        }

        return $cand;
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_memberships', 'neighborhood_group_id', 'user_id')
            ->withPivot('role', 'joined_at');
    }

    public function isMember(?User $user): bool
    {
        return $user !== null && $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * URL-safe slug of the scope (e.g. "District" -> "district"), or null
     * if no scope is set. Named to avoid reading as an Eloquent local scope.
     */
    public function scopeUrlSegment(): ?string
    {
        return $this->scope ? Str::slug($this->scope) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
