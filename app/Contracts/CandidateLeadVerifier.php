<?php

namespace App\Contracts;

use App\Models\CandidateLead;

interface CandidateLeadVerifier
{
    public function key(): string;

    /**
     * Return null when this verifier could not determine anything, so the
     * registry falls through to the next tier.
     *
     * @return array{status:string, confidence:float, reason:string,
     *   verified_payload:array<string,mixed>}|null
     */
    public function verify(CandidateLead $lead): ?array;
}
