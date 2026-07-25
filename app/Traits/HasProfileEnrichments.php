<?php

namespace App\Traits;

use App\Models\ProfileAddress;
use App\Models\ProfileContactMethod;
use App\Models\ProfileDonationLink;
use App\Models\ProfileEnrichmentRun;
use App\Models\ProfileSocialLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Enrichment relationships for profile models. Mirrors HasProfileBadges.
 *
 * Applied to Politician today; ready for Voter/Citizen when enrichment scope
 * expands (they currently lack a website_url to enrich against).
 */
trait HasProfileEnrichments
{
    public function enrichmentRuns(): MorphMany
    {
        return $this->morphMany(ProfileEnrichmentRun::class, 'profilable');
    }

    public function contactMethods(): MorphMany
    {
        return $this->morphMany(ProfileContactMethod::class, 'profilable');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(ProfileAddress::class, 'profilable');
    }

    public function socialLinks(): MorphMany
    {
        return $this->morphMany(ProfileSocialLink::class, 'profilable');
    }

    public function donationLinks(): MorphMany
    {
        return $this->morphMany(ProfileDonationLink::class, 'profilable');
    }

    /** Most recent enrichment run by enriched_at, or null if never enriched. */
    public function getLatestEnrichmentRunAttribute(): ?ProfileEnrichmentRun
    {
        return $this->enrichmentRuns()
            ->latest('enriched_at')
            ->first();
    }

    public function profileEnrichmentIsStale(int $hours = 48): bool
    {
        $run = $this->latestEnrichmentRun;

        return $run === null || $run->isStale($hours);
    }
}