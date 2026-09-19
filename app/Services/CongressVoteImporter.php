<?php

namespace App\Services;

use App\Models\CongressVote;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Imports roll-call votes straight from the chambers' own feeds (no API key needed):
 * Senate.gov's LIS vote XML and the House Clerk's roll-call XML. Each vote is stored
 * once with every member's position, keyed by Bioguide ID.
 *
 * The Senate feed identifies members by LIS id, so the congress-legislators dataset
 * supplies the LIS → Bioguide map.
 */
class CongressVoteImporter
{
    public const LEGISLATORS_URL = 'https://unitedstates.github.io/congress-legislators/legislators-current.json';

    private const USER_AGENT = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)';

    /** @var array<string, string>|null */
    private ?array $lisToBioguide = null;

    /**
     * @return array{imported: int, skipped: int, failed: int}
     */
    public function importSenate(int $congress, int $session, bool $refresh = false, ?int $limit = null): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0];

        $menu = $this->fetchXml("https://www.senate.gov/legislative/LIS/roll_call_lists/vote_menu_{$congress}_{$session}.xml");
        if ($menu === null) {
            $stats['failed']++;

            return $stats;
        }

        $numbers = [];
        foreach ($menu->votes->vote ?? [] as $vote) {
            $numbers[(int) $vote->vote_number] = trim((string) $vote->issue);
        }
        ksort($numbers);

        $have = $refresh ? [] : CongressVote::query()
            ->where(['chamber' => 'senate', 'congress' => $congress, 'session' => $session])
            ->pluck('roll_number')->flip()->all();

        foreach ($numbers as $number => $issue) {
            if (isset($have[$number])) {
                $stats['skipped']++;

                continue;
            }
            if ($limit !== null && $stats['imported'] >= $limit) {
                break;
            }

            $padded = str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $url = "https://www.senate.gov/legislative/LIS/roll_call_votes/vote{$congress}{$session}/vote_{$congress}_{$session}_{$padded}.xml";
            $xml = $this->fetchXml($url);
            if ($xml === null) {
                $stats['failed']++;

                continue;
            }

            $document = trim((string) $xml->document->document_name);
            $rows = [];
            foreach ($xml->members->member ?? [] as $member) {
                $bioguide = $this->senateBioguide((string) $member->lis_member_id);
                if ($bioguide !== null) {
                    $rows[] = [$bioguide, strtoupper(substr((string) $member->party, 0, 1)), $this->normalizeVote((string) $member->vote_cast)];
                }
            }

            $this->store([
                'chamber' => 'senate',
                'congress' => $congress,
                'session' => $session,
                'roll_number' => $number,
                'voted_at' => $this->parseDate((string) $xml->vote_date),
                'question' => $this->clean((string) $xml->question, 255),
                'title' => $this->clean((string) ($xml->vote_title ?: $xml->document->document_title)),
                'bill_number' => $this->clean($document !== '' ? $document : $issue, 60),
                'result' => $this->clean((string) $xml->vote_result, 150),
                'source_url' => $url,
            ], $rows);
            $stats['imported']++;
        }

        return $stats;
    }

    /**
     * The Clerk has no index of rolls, so walk forward from the last one stored until a roll 404s.
     *
     * @return array{imported: int, skipped: int, failed: int}
     */
    public function importHouse(int $congress, int $session, bool $refresh = false, ?int $limit = null): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        $year = 2 * $congress + 1787 + ($session - 1);

        $number = $refresh ? 1 : 1 + (int) CongressVote::query()
            ->where(['chamber' => 'house', 'congress' => $congress, 'session' => $session])
            ->max('roll_number');

        while ($limit === null || $stats['imported'] < $limit) {
            $url = 'https://clerk.house.gov/evs/'.$year.'/roll'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'.xml';
            $xml = $this->fetchXml($url, missingIsExpected: true);
            if ($xml === null || ! isset($xml->{'vote-metadata'})) {
                break; // Past the last roll call of the session.
            }

            $meta = $xml->{'vote-metadata'};
            $type = strtoupper(trim((string) $meta->{'vote-type'}));

            if ($type === 'QUORUM') {
                $stats['skipped']++; // Attendance checks, not policy votes.
                $number++;

                continue;
            }

            $rows = [];
            foreach ($xml->{'vote-data'}->{'recorded-vote'} ?? [] as $recorded) {
                $bioguide = trim((string) $recorded->legislator['name-id']);
                if ($bioguide !== '') {
                    $rows[] = [$bioguide, strtoupper(substr((string) $recorded->legislator['party'], 0, 1)), $this->normalizeVote((string) $recorded->vote)];
                }
            }

            $this->store([
                'chamber' => 'house',
                'congress' => $congress,
                'session' => $session,
                'roll_number' => $number,
                'voted_at' => $this->parseDate(trim((string) $meta->{'action-date'}.' '.(string) $meta->{'action-time'}['time-etz'])),
                'question' => $this->clean((string) $meta->{'vote-question'}, 255),
                'title' => $this->clean((string) $meta->{'vote-desc'}),
                'bill_number' => $this->clean((string) $meta->{'legis-num'}, 60),
                'result' => $this->clean((string) $meta->{'vote-result'}, 150),
                'source_url' => $url,
            ], $rows);
            $stats['imported']++;
            $number++;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows  [bioguide, party letter, normalised vote]
     */
    protected function store(array $attributes, array $rows): void
    {
        $tally = ['yea' => 0, 'nay' => 0, 'present' => 0, 'not_voting' => 0];
        $byParty = [];
        foreach ($rows as [, $party, $vote]) {
            if (isset($tally[$vote])) {
                $tally[$vote]++;
            }
            if (in_array($vote, ['yea', 'nay'], true) && in_array($party, ['D', 'R'], true)) {
                $byParty[$party][$vote] = ($byParty[$party][$vote] ?? 0) + 1;
            }
        }

        $positions = [];
        foreach ($byParty as $party => $counts) {
            $yea = $counts['yea'] ?? 0;
            $nay = $counts['nay'] ?? 0;
            if ($yea !== $nay) {
                $positions[$party] = $yea > $nay ? 'yea' : 'nay';
            }
        }

        DB::transaction(function () use ($attributes, $rows, $tally, $positions) {
            $vote = CongressVote::updateOrCreate(
                array_intersect_key($attributes, array_flip(['chamber', 'congress', 'session', 'roll_number'])),
                $attributes + [
                    'yeas' => $tally['yea'],
                    'nays' => $tally['nay'],
                    'present' => $tally['present'],
                    'not_voting' => $tally['not_voting'],
                    'party_positions' => $positions ?: null,
                ],
            );

            $vote->memberVotes()->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                $vote->memberVotes()->insert(array_map(fn (array $r) => [
                    'congress_vote_id' => $vote->id,
                    'bioguide_id' => $r[0],
                    'party' => $r[1] !== '' ? $r[1] : null,
                    'vote' => $r[2],
                ], $chunk));
            }
        });
    }

    public function normalizeVote(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'yea', 'aye', 'yes' => 'yea',
            'nay', 'no' => 'nay',
            'present' => 'present',
            'not voting' => 'not_voting',
            default => 'other', // speaker elections (candidate names), impeachment "guilty", etc.
        };
    }

    protected function senateBioguide(string $lisId): ?string
    {
        if ($this->lisToBioguide === null) {
            $this->lisToBioguide = [];

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->retry(2, 500, throw: false)->get(self::LEGISLATORS_URL);
                foreach ($response->successful() ? (array) $response->json() : [] as $row) {
                    $lis = $row['id']['lis'] ?? null;
                    $bioguide = $row['id']['bioguide'] ?? null;
                    if ($lis && $bioguide) {
                        $this->lisToBioguide[$lis] = $bioguide;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CongressVoteImporter: legislators feed unavailable', ['error' => $e->getMessage()]);
            }
        }

        return $this->lisToBioguide[trim($lisId)] ?? null;
    }

    protected function fetchXml(string $url, bool $missingIsExpected = false): ?\SimpleXMLElement
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->retry(2, 500, throw: false)->get($url);
        } catch (\Throwable $e) {
            Log::warning('CongressVoteImporter: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            if (! $missingIsExpected || $response->status() !== 404) {
                Log::warning('CongressVoteImporter: unexpected status', ['url' => $url, 'status' => $response->status()]);
            }

            return null;
        }

        $previous = libxml_use_internal_errors(true);
        // The House DTD reference is a relative path that never resolves; skip network/entity loading.
        $xml = simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    protected function parseDate(string $raw): ?Carbon
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, 'America/New_York')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function clean(string $value, ?int $max = null): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return null;
        }

        return $max !== null ? mb_substr($value, 0, $max) : $value;
    }
}
