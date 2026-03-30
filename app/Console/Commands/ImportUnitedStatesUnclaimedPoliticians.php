<?php

namespace App\Console\Commands;

use App\Contracts\PoliticianFetcher;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Services\PoliticianFetchers\CongressCombinedLegislatorsFetcher;
use App\Services\PoliticianFetchers\CongressCurrentLegislatorsFetcher;
use App\Services\PoliticianFetchers\CongressHistoricalLegislatorsFetcher;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportUnitedStatesUnclaimedPoliticians extends Command
{
    protected $signature = 'politicians:import-unclaimed-us
        {--fetcher=current : Comma-separated fetchers: current,historical,combined}
        {--state=* : Two-letter state filters (repeat option to include multiple states)}
        {--include-former : Include officials whose latest term has ended}
        {--current-url=https://unitedstates.github.io/congress-legislators/legislators-current.json : Override current legislators URL}
        {--historical-url=https://unitedstates.github.io/congress-legislators/legislators-historical.json : Override historical legislators URL}
        {--dry-run : Parse and report only}
        {--with-campaigns : Create one public preview campaign per imported profile}';

    protected $description = 'Import unclaimed U.S. federal politician profiles using pluggable fetchers.';

    /**
     * @var array<string, class-string<PoliticianFetcher>>
     */
    protected array $availableFetchers = [
        'current' => CongressCurrentLegislatorsFetcher::class,
        'historical' => CongressHistoricalLegislatorsFetcher::class,
        'combined' => CongressCombinedLegislatorsFetcher::class,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withCampaigns = (bool) $this->option('with-campaigns');
        $includeFormer = (bool) $this->option('include-former');

        $selectedFetchers = $this->parseFetcherSelection((string) $this->option('fetcher'));
        if ($selectedFetchers === []) {
            $this->error('No valid fetchers selected. Supported fetchers: current,historical,combined');

            return self::FAILURE;
        }

        $stateFilter = collect((array) $this->option('state'))
            ->map(fn ($state) => strtoupper(trim((string) $state)))
            ->filter(fn (string $state) => strlen($state) === 2)
            ->unique()
            ->values()
            ->all();

        $totals = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'campaigns_created' => 0,
            'report' => [],
        ];

        foreach ($selectedFetchers as $fetcherKey) {
            $result = $this->importFromFetcher(
                $fetcherKey,
                $stateFilter,
                $includeFormer,
                $dryRun,
                $withCampaigns,
            );

            if (! $result['ok']) {
                return self::FAILURE;
            }

            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];
            $totals['campaigns_created'] += $result['campaigns_created'];
            $totals['report'] = array_merge($totals['report'], $result['report']);
        }

        if ($dryRun) {
            $this->renderDryRunReport($totals['report']);
        }

        $this->info(sprintf(
            'U.S. import complete%s: %d created, %d updated, %d skipped, %d campaigns created.',
            $dryRun ? ' (dry-run)' : '',
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
            $totals['campaigns_created'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function parseFetcherSelection(string $selection): array
    {
        $keys = collect(explode(',', $selection))
            ->map(fn ($value) => strtolower(trim($value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        if ($keys === []) {
            $keys = ['current'];
        }

        $invalid = array_values(array_filter($keys, fn ($key) => ! array_key_exists($key, $this->availableFetchers)));
        if ($invalid !== []) {
            $this->error('Unknown fetcher(s): ' . implode(', ', $invalid));

            return [];
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $stateFilter
     * @return array{ok:bool,created:int,updated:int,skipped:int,campaigns_created:int,report:array<int,array<string,mixed>>}
     */
    protected function importFromFetcher(
        string $fetcherKey,
        array $stateFilter,
        bool $includeFormer,
        bool $dryRun,
        bool $withCampaigns,
    ): array {
        $fetcher = app($this->availableFetchers[$fetcherKey]);
        $fetchOptions = [
            'url' => $fetcherKey === 'historical'
                ? (string) $this->option('historical-url')
                : (string) $this->option('current-url'),
            'current_url' => (string) $this->option('current-url'),
            'historical_url' => (string) $this->option('historical-url'),
        ];

        try {
            $records = $fetcher->fetch($fetchOptions);
        } catch (\Throwable $exception) {
            $this->error('Fetcher ' . $fetcherKey . ' failed: ' . $exception->getMessage());

            return [
                'ok' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'campaigns_created' => 0,
                'report' => [],
            ];
        }

        $totals = [
            'ok' => true,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'campaigns_created' => 0,
            'report' => [],
        ];

        foreach ($records as $index => $record) {
            $result = $this->importRecord(
                $record,
                (int) $index,
                $stateFilter,
                $includeFormer,
                $dryRun,
                $withCampaigns,
                $fetcherKey,
            );

            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];
            $totals['campaigns_created'] += $result['campaigns_created'];
            $totals['report'] = array_merge($totals['report'], $result['report']);
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $stateFilter
     * @return array{created:int,updated:int,skipped:int,campaigns_created:int,report:array<int,array<string,mixed>>}
     */
    protected function importRecord(
        array $record,
        int $rowIndex,
        array $stateFilter,
        bool $includeFormer,
        bool $dryRun,
        bool $withCampaigns,
        string $fetcherKey,
    ): array {
        $source = (string) ($record['source'] ?? ('fetcher_' . $fetcherKey));
        $row = $record['row'] ?? null;

        if (! is_array($row)) {
            return $this->skipResult($dryRun, $source, $rowIndex, 'not an object');
        }

        $latestTerm = $this->latestTerm($row);
        if (! is_array($latestTerm)) {
            return $this->skipResult($dryRun, $source, $rowIndex, 'missing terms');
        }

        $state = strtoupper((string) ($latestTerm['state'] ?? ''));
        if ($state === '') {
            return $this->skipResult($dryRun, $source, $rowIndex, 'missing state');
        }

        if ($stateFilter !== [] && ! in_array($state, $stateFilter, true)) {
            return $this->emptyResult();
        }

        if (! $includeFormer && $this->isFormerOfficial($latestTerm)) {
            return $this->emptyResult();
        }

        $fullName = $this->fullName($row);
        if ($fullName === '') {
            return $this->skipResult($dryRun, $source, $rowIndex, 'missing full name');
        }

        $office = $this->politicalOffice($latestTerm);
        if ($office === null) {
            return $this->skipResult($dryRun, $source, $rowIndex, 'unsupported office type');
        }

        $district = $this->districtLabel($latestTerm, $state);
        $party = $this->nullableString($latestTerm['party'] ?? null);
        $website = $this->sanitizePublicWebsiteUrl($latestTerm['url'] ?? null);
        $city = $this->extractCityFromAddress($latestTerm);
        $bioguide = $this->nullableString($row['id']['bioguide'] ?? null);
        $photoUrl = $bioguide ? 'https://unitedstates.github.io/images/congress/225x275/' . $bioguide . '.jpg' : null;
        $bio = $this->buildExperienceSummary($row, $latestTerm);
        $videoLinks = $this->buildVideoLinks($row);

        $externalId = $bioguide
            ?: $this->nullableString($row['id']['govtrack'] ?? null)
            ?: substr(hash('sha256', $source . '|' . $fullName . '|' . $office . '|' . $state), 0, 24);

        $candidatePayload = [
            'source' => $source,
            'external_candidate_id' => $externalId,
            'full_name' => $fullName,
            'political_office' => $office,
            'governance_level' => 'Federal',
            'state' => $state,
            'district' => $district,
            'party_affiliation' => $party,
            'payload' => $row,
            'last_seen_at' => now(),
        ];

        $existingCandidate = ElectionCandidateRecord::query()
            ->where('source', $source)
            ->where('external_candidate_id', $externalId)
            ->first();

        if (! $dryRun) {
            ElectionCandidateRecord::updateOrCreate(
                [
                    'source' => $source,
                    'external_candidate_id' => $externalId,
                ],
                $candidatePayload,
            );
        }

        $report = [];

        if ($dryRun) {
            $report[] = [
                'status' => $existingCandidate ? 'updated' : 'created',
                'row' => $rowIndex,
                'entity' => 'candidate',
                'source' => $source,
                'key' => $source . ':' . $externalId,
                'name' => $fullName,
                'changes' => $this->buildCandidateDiff($existingCandidate, $candidatePayload),
            ];
        }

        $existing = Politician::query()
            ->whereNull('user_id')
            ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->first();

        $politicianPayload = [
            'full_name' => $fullName,
            'political_office' => $office,
            'governance_level' => 'Federal',
            'district' => $district,
            'party_affiliation' => $party,
            'state' => $state,
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
                $report[] = [
                    'status' => 'updated',
                    'row' => $rowIndex,
                    'entity' => 'politician',
                    'source' => $source,
                    'key' => (string) $existing->id,
                    'name' => $fullName,
                    'changes' => $this->buildPoliticianDiff($existing, $politicianPayload),
                ];
            }

            $campaignsCreated = 0;
            if (! $dryRun) {
                $existing->fill($politicianPayload);
                $existing->save();

                if ($withCampaigns) {
                    $campaignsCreated += $this->ensurePreviewCampaign($existing, $latestTerm, $state);
                }
            }

            return [
                'created' => 0,
                'updated' => 1,
                'skipped' => 0,
                'campaigns_created' => $campaignsCreated,
                'report' => $report,
            ];
        }

        if ($dryRun) {
            $report[] = [
                'status' => 'created',
                'row' => $rowIndex,
                'entity' => 'politician',
                'source' => $source,
                'key' => 'new',
                'name' => $fullName,
                'changes' => [],
            ];
        }

        $campaignsCreated = 0;
        if (! $dryRun) {
            $createdProfile = Politician::create($politicianPayload);

            if ($withCampaigns) {
                $campaignsCreated += $this->ensurePreviewCampaign($createdProfile, $latestTerm, $state);
            }
        }

        return [
            'created' => 1,
            'updated' => 0,
            'skipped' => 0,
            'campaigns_created' => $campaignsCreated,
            'report' => $report,
        ];
    }

    /**
     * @return array{created:int,updated:int,skipped:int,campaigns_created:int,report:array<int,array<string,mixed>>}
     */
    protected function emptyResult(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'campaigns_created' => 0,
            'report' => [],
        ];
    }

    /**
     * @return array{created:int,updated:int,skipped:int,campaigns_created:int,report:array<int,array<string,mixed>>}
     */
    protected function skipResult(bool $dryRun, string $source, int $rowIndex, string $reason): array
    {
        $result = $this->emptyResult();
        $result['skipped'] = 1;

        if ($dryRun) {
            $result['report'][] = [
                'status' => 'skipped',
                'row' => $rowIndex,
                'entity' => 'row',
                'source' => $source,
                'reason' => $reason,
            ];
        }

        return $result;
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
            $source = (string) ($entry['source'] ?? 'unknown');

            if ($status === 'SKIPPED') {
                $reason = (string) ($entry['reason'] ?? 'unknown');
                $this->line("[DRY-RUN][SKIP][{$entity}] source={$source} row={$row} reason={$reason}");
                continue;
            }

            $key = (string) ($entry['key'] ?? 'unknown');
            $name = (string) ($entry['name'] ?? '');
            $changes = $this->formatChanges($entry['changes'] ?? []);

            if ($status === 'UPDATED') {
                $this->line("[DRY-RUN][UPDATE][{$entity}] source={$source} row={$row} key={$key} name=\"{$name}\" changes={$changes}");
                continue;
            }

            $this->line("[DRY-RUN][CREATE][{$entity}] source={$source} row={$row} key={$key} name=\"{$name}\"");
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

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
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
     * @param  array<string, mixed>  $latestTerm
     */
    protected function isFormerOfficial(array $latestTerm): bool
    {
        $end = $this->nullableString($latestTerm['end'] ?? null);
        if ($end === null) {
            return false;
        }

        try {
            return Carbon::parse($end)->lt(now()->startOfDay());
        } catch (\Throwable) {
            return false;
        }
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
    protected function politicalOffice(array $term): ?string
    {
        $type = (string) ($term['type'] ?? '');

        if ($type === 'sen') {
            return 'U.S. Senator';
        }

        if ($type === 'rep') {
            return 'U.S. Representative';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $term
     */
    protected function districtLabel(array $term, string $state): ?string
    {
        if (($term['type'] ?? '') === 'sen') {
            return null;
        }

        $district = (string) ($term['district'] ?? '');
        if ($district === '') {
            return null;
        }

        return $state . '-' . str_pad($district, 2, '0', STR_PAD_LEFT);
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
            $parts[] = 'Latest listed term: ' . $start . ' to ' . $end . '.';
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

        $parts[] = 'This is an unclaimed profile generated from public U.S. legislative data and available for verified claim by the official campaign.';

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

    protected function sanitizePublicWebsiteUrl(mixed $value): ?string
    {
        $url = $this->nullableString($value);
        if ($url === null) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if ($host === 'api.congress.gov' || str_starts_with($path, '/v3')) {
            return null;
        }

        return $url;
    }

    /**
     * Returns 1 when a campaign is newly created, 0 when already present.
     *
     * @param  array<string, mixed>  $latestTerm
     */
    protected function ensurePreviewCampaign(Politician $politician, array $latestTerm, string $state): int
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
            'target_states' => [$state],
            'target_governance_levels' => ['Federal'],
        ]);

        return 1;
    }
}
