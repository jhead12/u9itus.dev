<?php

namespace App\Support;

use App\Models\ElectionCandidateRecord;

/**
 * Single-row mirror of MapStateCandidatesController's $discoveryVisible SQL predicate
 * (buildStateData(), ~line 255), for callers that already have a hydrated
 * ElectionCandidateRecord + its linked politician's active flag in hand (e.g. the review-
 * priority scorer) rather than a query builder to attach a WHERE clause to.
 *
 * Keep this in sync with that closure by hand — it is not generated from it. The rule: a
 * candidate_discovery-sourced row is only "vouched for" (and so eligible to render on the
 * public map) when it's linked to a still-active politician, or it carries an explicit
 * primary_result (i.e. it survived politicians:sync-primary-results).
 */
final class MapVisibility
{
    public static function discoveryVisible(ElectionCandidateRecord $ecr, bool $politicianIsActive): bool
    {
        if ((string) $ecr->source !== ElectionCandidateRecord::DISCOVERY_SOURCE) {
            return true;
        }

        if ($politicianIsActive) {
            return true;
        }

        $payload = is_array($ecr->payload) ? $ecr->payload : [];

        return trim((string) ($payload['primary_result'] ?? '')) !== '';
    }
}
