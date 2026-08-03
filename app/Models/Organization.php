<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A PAC, advocacy group, or other organization that can be linked to a
 * politician's donor snapshot via `pac_group_key` (see
 * config/pac_affiliations.php and App\Services\PacAffiliationClassifier).
 *
 * Claim/verification columns mirror Politician's — no claim UI is built yet,
 * this just keeps the schema ready for it.
 */
class Organization extends Model
{
    protected $table = 'organizations';

    protected $fillable = [
        'name',
        'slug',
        'org_type',
        'logo_url',
        'website_url',
        'description',
        'pac_group_key',
        'user_id',
        'verification_status',
        'verification_email',
        'verified_at',
        'verification_token',
        'claim_email',
        'claim_token',
        'claim_requested_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'claim_requested_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** FEC committees hand-linked to this org (see Committee::organization()). */
    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Build display-ready endorsement badges from a snapshot's raw
     * `pac_affiliations` array (shape: {group, label, matched_name, total}[],
     * as produced by App\Services\PacAffiliationClassifier::classify()).
     *
     * Multiple contributor rows can match the same group (e.g. "AIPAC" and
     * "NORPAC" both matching `aipac_pro_israel`) — these are deduped into a
     * single badge per group with a `matched_names` list, joined against
     * Organization rows by `pac_group_key` at read time (never a stored FK),
     * so historical snapshot rows keep working as new orgs are added later.
     *
     * @param  array<int, array{group?: string, label?: string, matched_name?: string, total?: mixed}>  $pacAffiliations
     * @return array<int, array{group: string, label: string, matched_names: array<int, string>, org_slug: ?string, logo_url: ?string, website_url: ?string, verification_status: ?string}>
     */
    public static function badgesForPacAffiliations(array $pacAffiliations): array
    {
        if (empty($pacAffiliations)) {
            return [];
        }

        $byGroup = [];
        foreach ($pacAffiliations as $match) {
            $group = trim((string) ($match['group'] ?? ''));
            if ($group === '') {
                continue;
            }

            $byGroup[$group] ??= [
                'label' => (string) ($match['label'] ?? $group),
                'matched_names' => [],
            ];

            $matchedName = trim((string) ($match['matched_name'] ?? ''));
            if ($matchedName !== '' && ! in_array($matchedName, $byGroup[$group]['matched_names'], true)) {
                $byGroup[$group]['matched_names'][] = $matchedName;
            }
        }

        if (empty($byGroup)) {
            return [];
        }

        $orgsByGroupKey = static::query()
            ->active()
            ->whereIn('pac_group_key', array_keys($byGroup))
            ->get()
            ->keyBy('pac_group_key');

        $badges = [];
        foreach ($byGroup as $group => $data) {
            $org = $orgsByGroupKey->get($group);

            $badges[] = [
                'group' => $group,
                'label' => $org?->name ?? $data['label'],
                'matched_names' => $data['matched_names'],
                'org_slug' => $org?->slug,
                'logo_url' => $org?->logo_url,
                'website_url' => $org?->website_url,
                'verification_status' => $org?->verification_status,
            ];
        }

        return $badges;
    }
}
