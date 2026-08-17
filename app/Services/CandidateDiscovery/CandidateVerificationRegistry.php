<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateLeadVerifier;
use App\Models\CandidateLead;
use Illuminate\Support\Facades\App;

/**
 * Ordered tier list of verifiers. verifyTiered() walks them in order and
 * returns the first non-null result — generalizes SyncPrimaryResults's
 * hardcoded "try Ballotpedia, then Wikipedia" fallthrough to N pluggable
 * tiers. The LLM tier is last and skipped entirely when $allowAi is false
 * (--skip-ai) or the tripped circuit breaker has disabled it for this run.
 */
class CandidateVerificationRegistry
{
    /** @var array<int, class-string<CandidateLeadVerifier>> */
    protected array $tiers = [
        BallotpediaLeadVerifier::class,
        WikipediaLeadVerifier::class,
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
