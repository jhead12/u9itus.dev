<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A durable, deduplicated registry of FEC committee IDs seen in
 * independent-expenditure (Schedule E) data — built up by
 * App\Services\FECService::resolveCommitteeNames() so a committee's name
 * only ever needs resolving once across all candidates/runs, and so it can
 * later be hand-linked to a curated Organization record (logo, website,
 * description).
 *
 * The read side — public /pacs/{id} pages — is served from the 1:1
 * `committee_profiles` row (see CommitteeProfile), populated nightly by
 * `committees:enrich-profiles`.
 */
class Committee extends Model
{
    protected $fillable = [
        'fec_committee_id',
        'name',
        'name_resolved_at',
        'organization_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'name_resolved_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CommitteeProfile::class);
    }

    /** Committees with an enriched profile row — the directory's candidate set. */
    public function scopeListable(Builder $query): Builder
    {
        return $query->whereHas('profile', fn ($q) => $q->whereNotNull('enriched_at'));
    }

    /**
     * The public URL path segment. The FEC committee ID is stable, unique and
     * already how everything else references a committee, so it is the route
     * key; the slug is decorative (prepended for readability/SEO) and ignored
     * on resolve.
     */
    public function getRouteKeyName(): string
    {
        return 'fec_committee_id';
    }

    public function publicSlug(): string
    {
        $name = trim((string) $this->name);
        if ($name === '' || $name === $this->fec_committee_id) {
            return $this->fec_committee_id;
        }

        return Str::slug(Str::limit($name, 60, '')) . '-' . $this->fec_committee_id;
    }
}
