<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cached donor/sponsor data for a politician, populated by the
 * politicians:enrich-donors artisan command (runs nightly via GitHub Actions).
 *
 * Storing this separately means profile page loads never hit external APIs —
 * the enrichment pipeline fetches in the background and writes here.
 */
class PoliticianDonorSnapshot extends Model
{
    protected $table = 'politician_donor_snapshots';

    protected $fillable = [
        'politician_id',
        'top_contributors',
        'top_industries',
        'fec_summary',
        'outside_spending',
        'pac_affiliations',
        'opensecrets_source_url',
        'fec_source_url',
        'election_cycle',
        'enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'top_contributors' => 'array',
            'top_industries'   => 'array',
            'fec_summary'      => 'array',
            'outside_spending' => 'array',
            'pac_affiliations' => 'array',
            'enriched_at'      => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function politician(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isStale(int $hours = 48): bool
    {
        return $this->enriched_at === null
            || $this->enriched_at->lt(now()->subHours($hours));
    }

    public function hasContributors(): bool
    {
        return !empty($this->top_contributors);
    }

    public function hasIndustries(): bool
    {
        return !empty($this->top_industries);
    }

    public function hasFecSummary(): bool
    {
        return !empty($this->fec_summary);
    }

    public function hasOutsideSpending(): bool
    {
        return !empty($this->outside_spending);
    }

    public function hasPacAffiliations(): bool
    {
        return !empty($this->pac_affiliations);
    }

    public function hasAnyData(): bool
    {
        return $this->hasContributors()
            || $this->hasIndustries()
            || $this->hasFecSummary()
            || $this->hasOutsideSpending();
    }
}

