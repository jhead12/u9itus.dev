<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enriched, display-ready detail for a Committee — the read side of the PAC
 * directory. Populated by `committees:enrich-profiles` (nightly), never at
 * request time. See the create_committee_profiles_table migration.
 */
class CommitteeProfile extends Model
{
    protected $fillable = [
        'committee_id',
        'fec_committee_id',
        'committee_type',
        'committee_type_full',
        'designation',
        'designation_full',
        'organization_type_full',
        'party',
        'is_super_pac',
        'is_hybrid',
        'treasurer_name',
        'street',
        'city',
        'state',
        'zip',
        'fec_website_url',
        'cycle',
        'total_receipts',
        'total_disbursements',
        'cash_on_hand',
        'debts_owed',
        'independent_expenditures',
        'coverage_end_date',
        'top_donors',
        'spending_by_race',
        'recent_expenditures',
        'enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'is_super_pac' => 'boolean',
            'is_hybrid' => 'boolean',
            'cycle' => 'integer',
            'total_receipts' => 'decimal:2',
            'total_disbursements' => 'decimal:2',
            'cash_on_hand' => 'decimal:2',
            'debts_owed' => 'decimal:2',
            'independent_expenditures' => 'decimal:2',
            'coverage_end_date' => 'date',
            'top_donors' => 'array',
            'spending_by_race' => 'array',
            'recent_expenditures' => 'array',
            'enriched_at' => 'datetime',
        ];
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function isStale(int $hours = 168): bool
    {
        return $this->enriched_at === null
            || $this->enriched_at->lt(now()->subHours($hours));
    }

    /** A short human label for the committee's kind, for badges. */
    public function kindLabel(): string
    {
        if ($this->is_super_pac) {
            return $this->is_hybrid ? 'Hybrid PAC (Super PAC)' : 'Super PAC';
        }

        return $this->committee_type_full
            ?: ($this->organization_type_full ?: 'Committee');
    }
}
