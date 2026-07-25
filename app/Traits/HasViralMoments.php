<?php

namespace App\Traits;

use App\Models\PoliticianViralMoment;
use App\Models\ViralMomentEnrichmentRun;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Viral-moment relationships for the Politician model.
 *
 * Mirrors HasProfileEnrichments, but uses HasMany (not MorphMany) because
 * politician_viral_moments + viral_moment_enrichment_runs are politician-scoped
 * via a direct foreign key — moments don't apply to voters/citizens the way
 * profile enrichment does.
 */
trait HasViralMoments
{
    public function viralMomentRuns(): HasMany
    {
        return $this->hasMany(ViralMomentEnrichmentRun::class);
    }

    public function viralMoments(): HasMany
    {
        return $this->hasMany(PoliticianViralMoment::class);
    }

    /** Most recent viral-moment run by enriched_at, or null if never enriched. */
    public function getLatestViralMomentRunAttribute(): ?ViralMomentEnrichmentRun
    {
        return $this->viralMomentRuns()
            ->latest('enriched_at')
            ->first();
    }

    public function viralMomentsAreStale(int $hours = 48): bool
    {
        $run = $this->latestViralMomentRun;

        return $run === null || $run->isStale($hours);
    }
}