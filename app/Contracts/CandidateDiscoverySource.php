<?php

namespace App\Contracts;

interface CandidateDiscoverySource
{
    public function key(): string;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array{full_name:string, state:?string, office_hint:?string,
     *   source_url:string, published_at:?\Carbon\Carbon, raw:array<string,mixed>}>
     */
    public function discover(array $options = []): array;
}
