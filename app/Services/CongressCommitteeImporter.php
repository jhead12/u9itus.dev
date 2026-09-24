<?php

namespace App\Services;

use App\Models\CongressCommitteeAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Current House, Senate and joint committee (and subcommittee) seats from the public
 * congress-legislators dataset. Seats are keyed by Bioguide ID, so a profile shows them
 * as soon as CongressMemberLinker has set politicians.bioguide_id. No API key needed.
 */
class CongressCommitteeImporter
{
    public const COMMITTEES_URL = 'https://unitedstates.github.io/congress-legislators/committees-current.json';

    public const MEMBERSHIP_URL = 'https://unitedstates.github.io/congress-legislators/committee-membership-current.json';

    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    /**
     * Replaces every stored seat with the current dataset.
     *
     * @return array{committees: int, seats: int, members: int}
     */
    public function import(bool $dryRun = false): array
    {
        $committees = $this->committeeIndex($this->fetchJson(self::COMMITTEES_URL));
        $rows = [];

        foreach ($this->fetchJson(self::MEMBERSHIP_URL) as $code => $members) {
            $committee = $committees[$code] ?? null;
            if ($committee === null || ! is_array($members)) {
                continue;
            }

            foreach ($members as $member) {
                $bioguide = trim((string) ($member['bioguide'] ?? ''));
                if ($bioguide === '') {
                    continue;
                }

                $rows["{$bioguide}|{$code}"] = $committee + [
                    'bioguide_id' => $bioguide,
                    'committee_code' => $code,
                    'title' => ($member['title'] ?? null) ?: null,
                    'rank' => isset($member['rank']) ? (int) $member['rank'] : null,
                    'side' => in_array($member['party'] ?? null, ['majority', 'minority'], true) ? $member['party'] : null,
                ];
            }
        }

        if ($rows === []) {
            // An empty feed would otherwise wipe every profile's committees.
            throw new RuntimeException('Committee membership feed returned no seats.');
        }

        if (! $dryRun) {
            DB::transaction(function () use ($rows) {
                CongressCommitteeAssignment::query()->delete();
                $now = now();
                foreach (array_chunk(array_values($rows), 500) as $chunk) {
                    CongressCommitteeAssignment::insert(array_map(fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now], $chunk));
                }
            });
        }

        return [
            'committees' => count(array_unique(array_column($rows, 'committee_code'))),
            'seats' => count($rows),
            'members' => count(array_unique(array_column($rows, 'bioguide_id'))),
        ];
    }

    /**
     * Committee code => shared row fields. Subcommittee codes are the parent's code plus
     * their own two digits, which is how the membership file keys them.
     *
     * @return array<string, array{parent_code: ?string, name: string, chamber: string, url: ?string}>
     */
    protected function committeeIndex(array $committees): array
    {
        $index = [];
        foreach ($committees as $committee) {
            $code = (string) ($committee['thomas_id'] ?? '');
            if ($code === '') {
                continue;
            }

            $chamber = in_array($committee['type'] ?? null, ['house', 'senate', 'joint'], true) ? $committee['type'] : 'joint';
            $url = ($committee['url'] ?? null) ?: null;
            $index[$code] = ['parent_code' => null, 'name' => $this->clean($committee['name'] ?? $code), 'chamber' => $chamber, 'url' => $url];

            foreach ($committee['subcommittees'] ?? [] as $sub) {
                $subCode = $code.($sub['thomas_id'] ?? '');
                if ($subCode === $code) {
                    continue;
                }
                $index[$subCode] = ['parent_code' => $code, 'name' => $this->clean($sub['name'] ?? $subCode), 'chamber' => $chamber, 'url' => $url];
            }
        }

        return $index;
    }

    protected function fetchJson(string $url): array
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->retry(2, 500, throw: false)->get($url);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("Could not load {$url} (HTTP {$response->status()}).");
        }

        return $response->json();
    }

    protected function clean(string $value): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 255);
    }
}
