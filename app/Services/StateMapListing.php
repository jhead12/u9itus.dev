<?php

namespace App\Services;

use App\Http\Controllers\Api\MapStateCandidatesController;
use Illuminate\Http\Request;

/**
 * Who the state map lists for an office — the same response the map's drawer renders, so an
 * audit compares against what users actually see rather than a parallel query.
 */
class StateMapListing
{
    /** @var array<string, array<string, mixed>> */
    private array $byState = [];

    /**
     * @return array{listed: string[], running: string[]} every candidate shown for the office, and those shown as running
     */
    public function office(string $state, string $officeLabel): array
    {
        $candidates = [];
        foreach ($this->state($state)['offices'] ?? [] as $office) {
            if (strcasecmp((string) ($office['office'] ?? ''), $officeLabel) === 0) {
                $candidates = array_merge($candidates, $office['candidates'] ?? []);
            }
        }

        return $this->names($candidates);
    }

    /**
     * @return array{listed: string[], running: string[]}
     */
    public function house(string $state, int $district): array
    {
        $candidates = [];
        foreach ($this->state($state)['house_candidates'] ?? [] as $key => $list) {
            if ((int) preg_replace('/\D/', '', (string) substr((string) $key, strrpos((string) $key, '-') + 1)) === $district) {
                $candidates = array_merge($candidates, $list);
            }
        }

        return $this->names($candidates);
    }

    /**
     * @return int[] districts the map has any card for
     */
    public function houseDistricts(string $state): array
    {
        $districts = [];
        foreach (array_keys($this->state($state)['house_candidates'] ?? []) as $key) {
            $districts[] = (int) preg_replace('/\D/', '', (string) substr((string) $key, strrpos((string) $key, '-') + 1));
        }

        return array_values(array_unique($districts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{listed: string[], running: string[]}
     */
    private function names(array $candidates): array
    {
        $listed = [];
        $running = [];
        foreach ($candidates as $candidate) {
            $listed[] = (string) $candidate['full_name'];
            if (! empty($candidate['is_running'])) {
                $running[] = (string) $candidate['full_name'];
            }
        }

        return ['listed' => $listed, 'running' => $running];
    }

    /**
     * @return array<string, mixed>
     */
    private function state(string $state): array
    {
        return $this->byState[$state] ??= app(MapStateCandidatesController::class)(Request::create('/', 'GET', ['state' => $state]))->getData(true);
    }
}
