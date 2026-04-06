<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUnverifiedPoliticianProfile extends Command
{
    protected $signature = 'politicians:create-unverified-profile
        {--website=https://jackson.asmdc.org/ : Official website URL}
        {--name=Dr. Corey A. Jackson : Full display name}
        {--office=Assemblymember : Political office title}
        {--level=State : Governance level}
        {--state=CA : Two-letter state code}
        {--district=AD-60 : District label}
        {--party=Democratic : Party affiliation}
        {--city=Moreno Valley : Primary city/location}
        {--bio=Official unclaimed profile imported from a public government website and pending verified claim by campaign staff. : Bio text}
        {--photo-url= : Optional profile photo URL}
        {--source=official_state_website : Candidate source/provider key}
        {--publish=1 : Publish profile page in public directory (1/0)}
        {--dry-run : Preview changes without persisting}';

    protected $description = 'Create or update a single unverified, unclaimed politician profile from an official website source.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $website = $this->sanitizeUrl((string) $this->option('website'));
        $name = trim((string) $this->option('name'));
        $office = trim((string) $this->option('office'));
        $level = trim((string) $this->option('level'));
        $state = strtoupper(trim((string) $this->option('state')));
        $district = $this->nullableString($this->option('district'));
        $party = $this->nullableString($this->option('party'));
        $city = $this->nullableString($this->option('city'));
        $bio = $this->nullableString($this->option('bio'));
        $photoUrl = $this->sanitizeUrl((string) $this->option('photo-url'));
        $source = $this->nullableString($this->option('source')) ?? 'official_state_website';
        $publish = filter_var($this->option('publish'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $publish = $publish ?? true;

        $inputError = $this->validateInput($name, $office, $state, $website);
        if ($inputError !== null) {
            $this->error($inputError);
            return self::FAILURE;
        }

        $externalId = substr(hash('sha256', implode('|', [$source, $website, $name, $state])), 0, 24);

        $candidatePayload = $this->buildCandidatePayload(
            $source,
            $externalId,
            $name,
            $office,
            $level,
            $state,
            $district,
            $party,
            (string) $website,
        );

        $politicianPayload = $this->buildPoliticianPayload(
            $name,
            $office,
            $level,
            $district,
            $party,
            $state,
            $city,
            (string) $website,
            $bio,
            $photoUrl,
            $publish,
        );

        $existingPolitician = $this->findExistingPolitician($name, $office, $state);
        $existingCandidate = $this->findExistingCandidate($source, $externalId);

        if ($dryRun) {
            $this->reportDryRun($source, $externalId, $name, $existingCandidate, $existingPolitician);
            return self::SUCCESS;
        }

        ElectionCandidateRecord::updateOrCreate(
            [
                'source' => $source,
                'external_candidate_id' => $externalId,
            ],
            $candidatePayload,
        );

        $profile = $this->upsertPoliticianProfile($existingPolitician, $politicianPayload);
        $action = $existingPolitician ? 'updated' : 'created';

        $this->info('Unverified profile ' . $action . ': ' . $profile->full_name . ' (ID ' . $profile->id . ')');
        $this->line('Website: ' . ($profile->website_url ?? 'n/a'));
        $this->line('Verification: unverified');

        return self::SUCCESS;
    }

    protected function validateInput(string $name, string $office, string $state, ?string $website): ?string
    {
        $error = null;

        if ($name === '') {
            $error = 'Name is required. Pass --name="Full Name".';
        } elseif ($office === '') {
            $error = 'Office is required. Pass --office="Office Title".';
        } elseif ($state === '' || strlen($state) !== 2) {
            $error = 'State must be a two-letter code, e.g. --state=CA.';
        } elseif ($website === null) {
            $error = 'Website must be a valid http(s) URL, e.g. --website=https://example.org/.';
        }

        return $error;
    }

    protected function buildCandidatePayload(
        string $source,
        string $externalId,
        string $name,
        string $office,
        string $level,
        string $state,
        ?string $district,
        ?string $party,
        string $website,
    ): array {
        $host = parse_url($website, PHP_URL_HOST);

        return [
            'source' => $source,
            'external_candidate_id' => $externalId,
            'full_name' => $name,
            'political_office' => $office,
            'governance_level' => $level,
            'state' => $state,
            'district' => $district,
            'party_affiliation' => $party,
            'payload' => [
                'website' => $website,
                'provider' => $source,
                'import_mode' => 'manual_single_profile',
                'host' => is_string($host) ? $host : null,
            ],
            'last_seen_at' => now(),
        ];
    }

    protected function buildPoliticianPayload(
        string $name,
        string $office,
        string $level,
        ?string $district,
        ?string $party,
        string $state,
        ?string $city,
        string $website,
        ?string $bio,
        ?string $photoUrl,
        bool $publish,
    ): array {
        return [
            'full_name' => $name,
            'political_office' => $office,
            'governance_level' => $level,
            'district' => $district,
            'party_affiliation' => $party,
            'state' => $state,
            'city' => $city,
            'website_url' => $website,
            'bio' => $bio,
            'profile_photo_url' => $photoUrl,
            'verified_official' => false,
            'verification_status' => 'unverified',
            'verified_at' => null,
            'is_active' => true,
            'page_published' => $publish,
        ];
    }

    protected function findExistingPolitician(string $name, string $office, string $state): ?Politician
    {
        return Politician::query()
            ->whereNull('user_id')
            ->whereRaw('LOWER(full_name) = ?', [strtolower($name)])
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->first();
    }

    protected function findExistingCandidate(string $source, string $externalId): ?ElectionCandidateRecord
    {
        return ElectionCandidateRecord::query()
            ->where('source', $source)
            ->where('external_candidate_id', $externalId)
            ->first();
    }

    protected function reportDryRun(
        string $source,
        string $externalId,
        string $name,
        ?ElectionCandidateRecord $existingCandidate,
        ?Politician $existingPolitician,
    ): void {
        $this->line('[DRY-RUN] Candidate record: ' . ($existingCandidate ? 'update' : 'create') . ' key=' . $source . ':' . $externalId);
        $this->line('[DRY-RUN] Politician profile: ' . ($existingPolitician ? 'update' : 'create') . ' name="' . $name . '"');
    }

    /**
     * @param  array<string, mixed>  $politicianPayload
     */
    protected function upsertPoliticianProfile(?Politician $existingPolitician, array $politicianPayload): Politician
    {
        if ($existingPolitician) {
            $existingPolitician->fill($politicianPayload);
            $existingPolitician->save();

            return $existingPolitician;
        }

        return Politician::create($politicianPayload);
    }

    protected function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    protected function sanitizeUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'https://' . ltrim($trimmed, '/');
        }

        $validated = filter_var($trimmed, FILTER_VALIDATE_URL);

        return is_string($validated) ? $validated : null;
    }
}
