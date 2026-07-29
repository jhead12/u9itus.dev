<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    /** Alias for admin() so this model can stand in as a CivicEvent host
     *  alongside Citizen/Politician, which both expose user(). */
    public function user(): BelongsTo
    {
        return $this->admin();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_memberships', 'neighborhood_group_id', 'user_id')
            ->withPivot('role', 'joined_at');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(CivicEvent::class, 'host');
    }

    public function isMember(?User $user): bool
    {
        return $user !== null && $this->members()->where('user_id', $user->id)->exists();
    }

    /** The founding creator — sole authority to delete the group or change
     *  another member's role. Never removable/demotable via member management. */
    public function isOwner(?User $user): bool
    {
        return $user !== null && (int) $this->admin_user_id === (int) $user->id;
    }

    /** Owner, or a member promoted to the 'admin' pivot role. Can edit
     *  settings, manage events, and remove regular members — but not
     *  delete the group or change anyone's role (owner-only). */
    public function isAdmin(?User $user): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        return $user !== null && $this->members()
            ->where('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
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
