<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportCaliforniaUnclaimedPoliticians extends Command
{
    protected $signature = 'politicians:import-unclaimed-ca
        {--source-url=https://unitedstates.github.io/congress-legislators/legislators-current.json : JSON feed URL}
        {--dry-run : Parse and report only}
        {--with-campaigns : Create one public preview campaign per imported profile}';

    protected $description = 'Import California federal politicians as unclaimed public profiles from congress-legislators data.';

    public function handle(): int
    {
        $url = (string) $this->option('source-url');
        $dryRun = (bool) $this->option('dry-run');
        $withCampaigns = (bool) $this->option('with-campaigns');

        $response = Http::timeout(30)->get($url);

        if (! $response->ok()) {
            $this->error('Unable to fetch source JSON: HTTP ' . $response->status());

            return self::FAILURE;
        }

        $rows = $response->json();
        if (is_array($rows) && array_key_exists('legislators', $rows) && is_array($rows['legislators'])) {
            $rows = $rows['legislators'];
        }

        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            $this->error('Unexpected response shape. Expected a JSON array.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $campaignsCreated = 0;
        $dryRunReport = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $skipped++;
                if ($dryRun) {
                    $dryRunReport[] = [
                        'status' => 'skipped',
                        'row' => $index,
                        'entity' => 'row',
                        'reason' => 'not an object',
                    ];
                }
                continue;
            }

            $latestTerm = $this->latestTerm($row);
            if (! is_array($latestTerm)) {
                $skipped++;
                if ($dryRun) {
                    $dryRunReport[] = [
                        'status' => 'skipped',
                        'row' => $index,
                        'entity' => 'row',
                        'reason' => 'missing terms',
                    ];
                }
                continue;
            }

            if (strtoupper((string) ($latestTerm['state'] ?? '')) !== 'CA') {
                continue;
            }

            $fullName = $this->fullName($row);
            if ($fullName === '') {
                $skipped++;
                if ($dryRun) {
                    $dryRunReport[] = [
                        'status' => 'skipped',
                        'row' => $index,
                        'entity' => 'row',
                        'reason' => 'missing full name',
                    ];
                }
                continue;
            }

            $office = $this->politicalOffice($latestTerm);
            $district = $this->districtLabel($latestTerm);
            $party = $this->nullableString($latestTerm['party'] ?? null);
            $website = $this->nullableString($latestTerm['url'] ?? null);
            $city = $this->extractCityFromAddress($latestTerm);
            $bioguide = $this->nullableString($row['id']['bioguide'] ?? null);
            $photoUrl = $bioguide ? 'https://unitedstates.github.io/images/congress/225x275/' . $bioguide . '.jpg' : null;
            $bio = $this->buildExperienceSummary($row, $latestTerm);
            $videoLinks = $this->buildVideoLinks($row);

            $externalId = $bioguide
                ?: $this->nullableString($row['id']['govtrack'] ?? null)
                ?: substr(hash('sha256', 'ca|' . $fullName . '|' . $office), 0, 24);

            $candidatePayload = [
                'source' => 'congress_legislators',
                'external_candidate_id' => $externalId,
                'full_name' => $fullName,
                'political_office' => $office,
                'governance_level' => 'Federal',
                'state' => 'CA',
                'district' => $district,
                'party_affiliation' => $party,
                'payload' => $row,
                'last_seen_at' => now(),
            ];

            $existingCandidate = ElectionCandidateRecord::query()
                ->where('source', 'congress_legislators')
                ->where('external_candidate_id', $externalId)
                ->first();

            if (! $dryRun) {
                ElectionCandidateRecord::updateOrCreate(
                    [
                        'source' => 'congress_legislators',
                        'external_candidate_id' => $externalId,
                    ],
                    $candidatePayload,
                );
            }

            if ($dryRun) {
                $dryRunReport[] = [
                    'status' => $existingCandidate ? 'updated' : 'created',
                    'row' => $index,
                    'entity' => 'candidate',
                    'key' => 'congress_legislators:' . $externalId,
                    'name' => $fullName,
                    'changes' => $this->buildCandidateDiff($existingCandidate, $candidatePayload),
                ];
            }

            $existing = Politician::query()
                ->whereNull('user_id')
                ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
                ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
                ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', ['CA'])
                ->first();

            $politicianPayload = [
                'full_name' => $fullName,
                'political_office' => $office,
                'governance_level' => 'Federal',
                'district' => $district,
                'party_affiliation' => $party,
                'state' => 'CA',
                'city' => $city,
                'website_url' => $website,
                'video_links' => $videoLinks,
                'bio' => $bio,
                'profile_photo_url' => $photoUrl,
                'verified_official' => true,
                'is_active' => true,
                'page_published' => true,
            ];

            if ($existing) {
                if ($dryRun) {
                    $dryRunReport[] = [
                        'status' => 'updated',
                        'row' => $index,
                        'entity' => 'politician',
                        'key' => (string) $existing->id,
                        'name' => $fullName,
                        'changes' => $this->buildPoliticianDiff($existing, $politicianPayload),
                    ];
                }

                if (! $dryRun) {
                    $existing->fill($politicianPayload);
                    $existing->save();

                    if ($withCampaigns) {
                        $campaignsCreated += $this->ensurePreviewCampaign($existing, $latestTerm);
                    }
                }
                $updated++;
                continue;
            }

            if ($dryRun) {
                $dryRunReport[] = [
                    'status' => 'created',
                    'row' => $index,
                    'entity' => 'politician',
                    'key' => 'new',
                    'name' => $fullName,
                    'changes' => [],
                ];
            }

            if (! $dryRun) {
                $createdProfile = Politician::create($politicianPayload);

                if ($withCampaigns) {
                    $campaignsCreated += $this->ensurePreviewCampaign($createdProfile, $latestTerm);
                }
            }

            $created++;
        }

        if ($dryRun) {
            $this->renderDryRunReport($dryRunReport);
        }

        $this->info(sprintf(
            'California import complete%s: %d created, %d updated, %d skipped, %d campaigns created.',
            $dryRun ? ' (dry-run)' : '',
            $created,
            $updated,
            $skipped,
            $campaignsCreated,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $report
     */
    protected function renderDryRunReport(array $report): void
    {
        foreach ($report as $entry) {
            $status = strtoupper((string) ($entry['status'] ?? 'SKIPPED'));
            $row = (int) ($entry['row'] ?? -1);
            $entity = (string) ($entry['entity'] ?? 'row');

            if ($status === 'SKIPPED') {
                $reason = (string) ($entry['reason'] ?? 'unknown');
                $this->line("[DRY-RUN][SKIP][{$entity}] row={$row} reason={$reason}");
                continue;
            }

            $key = (string) ($entry['key'] ?? 'unknown');
            $name = (string) ($entry['name'] ?? '');
            $changes = $this->formatChanges($entry['changes'] ?? []);

            if ($status === 'UPDATED') {
                $this->line("[DRY-RUN][UPDATE][{$entity}] row={$row} key={$key} name=\"{$name}\" changes={$changes}");
                continue;
            }

            $this->line("[DRY-RUN][CREATE][{$entity}] row={$row} key={$key} name=\"{$name}\"");
        }
    }

    /**
     * @param  array<string, mixed>|mixed  $changes
     */
    protected function formatChanges(mixed $changes): string
    {
        if (! is_array($changes) || $changes === []) {
            return 'none';
        }

        $parts = [];
        foreach ($changes as $field => $delta) {
            if (! is_array($delta)) {
                continue;
            }

            $from = $this->formatScalar($delta['from'] ?? null);
            $to = $this->formatScalar($delta['to'] ?? null);
            $parts[] = $field . ':' . $from . '=>'. $to;
        }

        return $parts === [] ? 'none' : implode(';', $parts);
    }

    protected function formatScalar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: 'array';
        }

        return str_replace(['\n', '\r', ';'], [' ', ' ', ','], (string) $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function buildCandidateDiff(?ElectionCandidateRecord $existing, array $payload): array
    {
        if (! $existing) {
            return [];
        }

        $fields = [
            'full_name',
            'political_office',
            'governance_level',
            'state',
            'district',
            'party_affiliation',
        ];

        $changes = [];
        foreach ($fields as $field) {
            $from = $existing->getAttribute($field);
            $to = $payload[$field] ?? null;

            if ((string) ($from ?? '') === (string) ($to ?? '')) {
                continue;
            }

            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function buildPoliticianDiff(Politician $existing, array $payload): array
    {
        $fields = [
            'full_name',
            'political_office',
            'district',
            'party_affiliation',
            'state',
            'city',
            'website_url',
            'bio',
            'profile_photo_url',
            'video_links',
        ];

        $changes = [];
        foreach ($fields as $field) {
            $from = $existing->getAttribute($field);
            $to = $payload[$field] ?? null;

            if (is_array($from) || is_array($to)) {
                if (json_encode($from) === json_encode($to)) {
                    continue;
                }
            } elseif ((string) ($from ?? '') === (string) ($to ?? '')) {
                continue;
            }

            $changes[$field] = ['from' => $from, 'to' => $to];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function latestTerm(array $row): ?array
    {
        $terms = $row['terms'] ?? null;

        if (! is_array($terms) || $terms === []) {
            return null;
        }

        usort($terms, function ($a, $b) {
            $left = is_array($a) ? (string) ($a['start'] ?? '') : '';
            $right = is_array($b) ? (string) ($b['start'] ?? '') : '';

            return strcmp($right, $left);
        });

        return is_array($terms[0]) ? $terms[0] : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function fullName(array $row): string
    {
        $name = $row['name'] ?? [];
        if (! is_array($name)) {
            return '';
        }

        $official = trim((string) ($name['official_full'] ?? ''));
        if ($official !== '') {
            return $official;
        }

        return trim(implode(' ', array_filter([
            (string) ($name['first'] ?? ''),
            (string) ($name['middle'] ?? ''),
            (string) ($name['last'] ?? ''),
            (string) ($name['suffix'] ?? ''),
        ])));
    }

    /**
     * @param  array<string, mixed>  $term
     */
    protected function politicalOffice(array $term): string
    {
        return (($term['type'] ?? '') === 'sen') ? 'U.S. Senator' : 'U.S. Representative';
    }

    /**
     * @param  array<string, mixed>  $term
     */
    protected function districtLabel(array $term): ?string
    {
        if (($term['type'] ?? '') === 'sen') {
            // Senators represent the entire state — no district label
            return null;
        }

        $district = (string) ($term['district'] ?? '');
        if ($district === '') {
            return null;
        }

        return 'CA-' . str_pad($district, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $latestTerm
     */
    protected function buildExperienceSummary(array $row, array $latestTerm): string
    {
        $terms = $row['terms'] ?? [];
        $count = is_array($terms) ? count($terms) : 0;
        $party = $this->nullableString($latestTerm['party'] ?? null);
        $start = $this->nullableString($latestTerm['start'] ?? null);
        $end = $this->nullableString($latestTerm['end'] ?? null);
        $contactForm = $this->nullableString($latestTerm['contact_form'] ?? null);
        $phone = $this->nullableString($latestTerm['phone'] ?? null);
        $birthday = $this->nullableString($row['bio']['birthday'] ?? null);

        $parts = [];

        if ($count > 0) {
            $parts[] = 'Public record shows ' . $count . ' congressional term' . ($count === 1 ? '' : 's') . ' of service.';
        }

        if ($party) {
            $parts[] = 'Party affiliation: ' . $party . '.';
        }

        if ($start && $end) {
            $parts[] = 'Current listed term: ' . $start . ' to ' . $end . '.';
        }

        if ($birthday) {
            $parts[] = 'Date of birth (public record): ' . $birthday . '.';
        }

        if ($phone) {
            $parts[] = 'Congressional office phone: ' . $phone . '.';
        }

        if ($contactForm) {
            $parts[] = 'Official contact form: ' . $contactForm . '.';
        }

        $parts[] = 'This is an unclaimed profile generated from public legislative data and available for verified claim by the official campaign.';

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $term
     */
    protected function extractCityFromAddress(array $term): ?string
    {
        $address = $this->nullableString($term['address'] ?? null);
        if ($address === null) {
            return null;
        }

        // Typical source format: "123 Main St, City, ST 12345"
        $parts = array_map('trim', explode(',', $address));

        if (count($parts) < 2) {
            return null;
        }

        return $this->nullableString($parts[1] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{url: string, title: string}>
     */
    protected function buildVideoLinks(array $row): array
    {
        $social = $row['social'] ?? null;
        if (! is_array($social)) {
            return [];
        }

        $links = [];

        $youtubeHandle = $this->nullableString($social['youtube'] ?? null);
        if ($youtubeHandle) {
            $links[] = [
                'url' => 'https://www.youtube.com/@' . ltrim($youtubeHandle, '@'),
                'title' => 'Official YouTube Channel',
            ];
        }

        $youtubeId = $this->nullableString($social['youtube_id'] ?? null);
        if ($youtubeId) {
            $links[] = [
                'url' => 'https://www.youtube.com/channel/' . $youtubeId,
                'title' => 'Official YouTube Channel',
            ];
        }

        $cspan = $this->nullableString($social['cspan'] ?? null);
        if ($cspan) {
            $links[] = [
                'url' => 'https://www.c-span.org/person/?' . $cspan,
                'title' => 'C-SPAN Appearances',
            ];
        }

        // De-duplicate links by URL while preserving order.
        $seen = [];

        return array_values(array_filter($links, function (array $link) use (&$seen): bool {
            $url = $link['url'];
            if (isset($seen[$url])) {
                return false;
            }

            $seen[$url] = true;

            return true;
        }));
    }

    protected function nullableString(mixed $value): ?string
    {
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    /**
     * Returns 1 when a campaign is newly created, 0 when already present.
     *
     * @param  array<string, mixed>  $latestTerm
     */
    protected function ensurePreviewCampaign(Politician $politician, array $latestTerm): int
    {
        $termStart = $this->nullableString($latestTerm['start'] ?? null);
        $title = 'Public Profile Preview: ' . ($termStart ? 'Term ' . substr($termStart, 0, 4) : 'Current Term');

        $existing = PoliticalCampaign::query()
            ->where('politician_id', $politician->id)
            ->where('title', $title)
            ->first();

        if ($existing) {
            return 0;
        }

        PoliticalCampaign::create([
            'politician_id' => $politician->id,
            'title' => $title,
            'message_summary' => 'Public information campaign preview generated from official legislative data. Claim this profile to publish official campaign content.',
            'campaign_type' => CampaignType::Video->value,
            'governance_level' => 'Federal',
            'media_url' => null,
            'thumbnail_url' => $politician->profile_photo_url,
            'revenue_per_view' => 0.60,
            'voter_payout_per_view' => 0.25,
            'total_budget' => 0,
            'amount_spent' => 0,
            'total_views_requested' => 0,
            'views_completed' => 0,
            'min_watch_time_percent' => 100,
            'status' => CampaignStatus::Draft->value,
            'approval_status' => ApprovalStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'target_states' => ['CA'],
            'target_governance_levels' => ['Federal'],
        ]);

        return 1;
    }
}
