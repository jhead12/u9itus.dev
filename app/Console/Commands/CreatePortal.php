<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff provisioning for the white-label voter portal — the "portals are
 * staff-provisioned per client" step from the product plan. Creates (or,
 * re-run with the same slug, updates) the Organization row the portal lives
 * on, so there is no need for tinker or raw SQL to launch a client.
 *
 * Idempotent like admin:create: re-running only overwrites the fields whose
 * options were passed, and never replaces a layout the client may already
 * have edited unless --reset-layout is given.
 */
class CreatePortal extends Command
{
    protected $signature = 'portal:create
                            {name : Organization display name}
                            {--slug= : URL slug (defaults to a slug of the name)}
                            {--type= : Org type — one of config/organizations.php types (required for a new portal)}
                            {--state= : Two-letter target state, e.g. CA}
                            {--district= : Target district, e.g. 12 or CA-12}
                            {--owner= : Email of an existing user who will own and edit the portal}
                            {--logo= : Logo image URL}
                            {--color= : Primary brand color as hex, e.g. #1d4ed8}
                            {--website= : Organization website URL}
                            {--banner= : Hero banner text}
                            {--publish : Make the portal publicly visible now}
                            {--reset-layout : Replace an existing layout with the branded starter layout}';

    protected $description = 'Create or update a white-label voter portal for an organization (union, PAC, nonprofit) and print its builder and public links.';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $slug = Str::slug((string) ($this->option('slug') ?: $name));
        $types = array_keys(config('organizations.types', []));

        $input = [
            'name' => $name,
            'slug' => $slug,
            'type' => $this->option('type'),
            'state' => $this->option('state') ? strtoupper($this->option('state')) : null,
            'district' => $this->option('district'),
            'owner' => $this->option('owner'),
            'logo' => $this->option('logo'),
            'color' => $this->option('color'),
            'website' => $this->option('website'),
        ];

        $existing = Organization::where('slug', $slug)->first();

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'type' => [$existing ? 'nullable' : 'required', Rule::in($types)],
            'state' => ['nullable', 'alpha', 'size:2'],
            'district' => ['nullable', 'string', 'max:32'],
            'owner' => ['nullable', 'email'],
            'logo' => ['nullable', 'url'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'website' => ['nullable', 'url', 'max:512'],
        ], [
            'type.in' => 'The type must be one of: '.implode(', ', $types).'.',
            'type.required' => 'A new portal needs --type (one of: '.implode(', ', $types).').',
            'color.regex' => 'The color must be a 6-digit hex value like #1d4ed8.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $owner = null;
        if ($input['owner']) {
            $owner = User::where('email', $input['owner'])->first();
            if (! $owner) {
                $this->error("No user found with email {$input['owner']}. Create the account first, then re-run.");

                return self::FAILURE;
            }
        }

        // Only the options actually passed overwrite an existing portal.
        $attributes = array_filter([
            'name' => $name,
            'org_type' => $input['type'],
            'target_state' => $input['state'],
            'target_district' => $input['district'],
            'user_id' => $owner?->id,
            'logo_url' => $input['logo'],
            'website_url' => $input['website'],
        ], fn ($value) => $value !== null);

        if ($this->option('publish')) {
            $attributes['portal_published'] = true;
        }

        $organization = $existing ?? new Organization(['slug' => $slug, 'is_active' => true]);
        $organization->fill($attributes);

        if (! $organization->portal_layout || $this->option('reset-layout')) {
            $organization->portal_layout = $this->starterLayout(
                $name,
                $input['logo'] ?? $organization->logo_url,
                $input['color'],
                $this->option('banner'),
            );
        }

        $organization->save();

        $this->info(($existing ? '✓ Updated' : '✓ Created')." portal for {$organization->name} ({$organization->slug}).");
        $this->line('  Type:      '.(config("organizations.types.{$organization->org_type}.label") ?? $organization->org_type));
        $this->line('  Target:    '.($organization->target_state ?? '—').($organization->target_district ? " / {$organization->target_district}" : ''));
        $this->line('  Owner:     '.($organization->user?->email ?? '— (staff only until --owner is set)'));
        $this->line('  Published: '.($organization->portal_published ? 'yes' : 'no (re-run with --publish when ready)'));
        $this->line('  Builder:   '.route('portal.builder.edit', $organization));
        $this->line('  Public:    '.route('portal.show', $organization));
        $this->line('  Embed:     '.route('portal.embed', $organization));

        if (! $organization->canEndorseCandidates()) {
            $this->warn('  This org type can only take ballot-measure positions, not candidate endorsements.');
        }

        return self::SUCCESS;
    }

    /**
     * The Puck Data tree a new client opens in the builder: branded hero
     * plus the data-driven blocks, so they start from their own page
     * rather than a blank canvas. Block types match resources/js/portal-builder/blocks.jsx.
     */
    protected function starterLayout(string $name, ?string $logo, ?string $color, ?string $banner): array
    {
        return [
            'root' => ['props' => (object) []],
            'content' => [
                ['type' => 'Hero', 'props' => array_filter([
                    'id' => 'Hero-starter',
                    'orgName' => $name,
                    'logoUrl' => $logo,
                    'bannerText' => $banner ?: 'Know before you vote.',
                    'primaryColor' => $color ?: '#4f46e5',
                ], fn ($value) => $value !== null)],
                ['type' => 'CandidateList', 'props' => ['id' => 'CandidateList-starter', 'heading' => 'Candidates', 'limit' => 12]],
                ['type' => 'BallotMeasureList', 'props' => ['id' => 'BallotMeasureList-starter', 'heading' => 'Ballot Measures']],
                ['type' => 'EndorsementBadges', 'props' => ['id' => 'EndorsementBadges-starter', 'heading' => 'Our Endorsements']],
            ],
        ];
    }
}
