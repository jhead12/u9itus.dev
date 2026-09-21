<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateLeadVerifier;
use App\Models\CandidateLead;
use Illuminate\Support\Facades\App;

/**
 * Ordered tier list of verifiers. verifyTiered() walks them in order and
 * returns the first non-null result. The LLM tier is skipped entirely when
 * $allowAi is false (--skip-ai) or the tripped circuit breaker has disabled it
 * for this run.
 *
 * There is deliberately no Ballotpedia or Wikipedia page-text tier. Keyword-matching
 * a candidate's page reads their endorsements table and earlier cycles as their own
 * result ("lost primary" flagged Ken Paxton eliminated) and a bare "advance" made
 * Angela Paxton a U.S. Senate candidate. A primary result comes only from the race's
 * Wikipedia article, via politicians:sync-primary-results.
 */
class CandidateVerificationRegistry
{
    /** @var array<int, class-string<CandidateLeadVerifier>> */
    protected array $tiers = [
        LlmCandidateLeadVerifier::class,
    ];

    /**
     * @return array{status:string, confidence:float, reason:string,
     *   verified_payload:array<string,mixed>, verifier_key:string}|null
     */
    public function verifyTiered(CandidateLead $lead, bool $allowAi = true): ?array
    {
        foreach ($this->tiers as $class) {
            /** @var CandidateLeadVerifier $verifier */
            $verifier = App::make($class);

            if ($verifier->key() === 'llm_anthropic' && ! $allowAi) {
                continue;
            }

            $result = $verifier->verify($lead);
            if ($result !== null) {
                $result['verifier_key'] = $verifier->key();
                return $result;
            }
        }

        return null;
    }
}
