<?php

namespace App\Support;

/**
 * Plain-language descriptions of the offices voters compare. General
 * descriptions only: exact duties, terms, and powers vary by state and city,
 * and the glossary page says so. Shared by the map's statewide panel and the
 * /compare page, printed guide, and glossary.
 */
final class OfficeGlossary
{
    /** @var array<string, array{title: string, description: string}> keyed by slug */
    public const ENTRIES = [
        'us-representative' => ['title' => 'U.S. Representative',
            'description' => 'U.S. Representatives represent one congressional district in the U.S. House of Representatives and serve 2-year terms. They vote on federal laws and the federal budget, and bills to raise federal revenue must start in the House.'],
        'us-senator' => ['title' => 'U.S. Senator',
            'description' => 'U.S. Senators represent the entire state in the U.S. Senate, serving 6-year terms. Each state elects two, who vote on federal legislation, confirm presidential nominees, and ratify treaties.'],
        'governor' => ['title' => 'Governor',
            'description' => 'The Governor is the chief executive of the state. They sign or veto legislation, command the state National Guard, and oversee state executive agencies. In most states, other executive officers such as the attorney general are elected separately.'],
        'lieutenant-governor' => ['title' => 'Lieutenant Governor',
            'description' => 'The Lieutenant Governor acts as second-in-command to the Governor, presides over the state senate in many states, and assumes the governorship if needed.'],
        'attorney-general' => ['title' => 'Attorney General',
            'description' => 'The Attorney General is the state\'s chief law-enforcement officer and top legal advisor, representing the state in litigation and leading consumer-protection efforts.'],
        'state-treasurer' => ['title' => 'State Treasurer',
            'description' => 'The State Treasurer manages the state\'s financial assets, oversees investments of public funds, and is responsible for debt management and cash flow.'],
        'state-controller' => ['title' => 'State Controller',
            'description' => 'The State Controller (or Comptroller) audits state spending, issues warrants for payments from the state treasury, and oversees accounting of public funds.'],
        'secretary-of-state' => ['title' => 'Secretary of State',
            'description' => 'The Secretary of State manages elections, maintains official state records and business filings, and certifies election results.'],
        'other-statewide' => ['title' => 'Other statewide offices',
            'description' => 'Other statewide executive offices vary by state and may include commissioners, auditors, and other elected or appointed officials.'],
        'mayor' => ['title' => 'Mayor',
            'description' => 'The Mayor leads city government. Depending on the city, the mayor may run city departments, propose the budget, and sign or veto council decisions, or mainly chair the city council.'],
        'city-council-member' => ['title' => 'City Council Member',
            'description' => 'City council members make local laws (ordinances), approve the city budget, and oversee city services. Some represent one district or ward; others are elected citywide.'],
        'trial-court-judge' => ['title' => 'Trial court judge',
            'description' => 'Trial court judges (called superior, district, or circuit court judges, depending on the state) decide civil and criminal cases and apply the law to the facts in each case. Judicial ethics rules generally bar judges and judicial candidates from promising how they would rule.'],
    ];

    /** The map's statewide panel, keyed by its office group names (unchanged wording). */
    public static function statewideRoles(): array
    {
        return [
            'U.S. Senators' => self::ENTRIES['us-senator']['description'],
            ...collect(['Governor' => 'governor', 'Lieutenant Governor' => 'lieutenant-governor', 'Attorney General' => 'attorney-general',
                'State Treasurer' => 'state-treasurer', 'State Controller' => 'state-controller', 'Secretary of State' => 'secretary-of-state',
                'Other Statewide' => 'other-statewide'])->map(fn ($slug) => self::ENTRIES[$slug]['description'])->all(),
        ];
    }

    /**
     * The office's description for a place: a sourced city note, then a sourced
     * state note (config/office_glossary.php), else the general description with
     * scope 'general' so readers are told it may not match their state or city.
     *
     * @return array{slug: string, title: string, description: string, scope: string, place: ?string, source_label: ?string, source_url: ?string, reviewed_at: ?string}|null
     */
    public static function forPlace(?string $office, ?string $state, ?string $city = null): ?array
    {
        $entry = self::for($office);
        if (! $entry) return null;
        $state = strtoupper(trim((string) $state));
        $city = trim((string) $city);
        $overrides = config('office_glossary.overrides', []);
        $cityNote = null;
        foreach ($overrides[$state]['cities'] ?? [] as $name => $notes) {
            if ($city !== '' && strcasecmp($name, $city) === 0 && isset($notes[$entry['slug']])) {
                [$cityNote, $city] = [$notes[$entry['slug']], $name];
            }
        }
        $note = $cityNote ?? ($overrides[$state][$entry['slug']] ?? null);
        $place = $cityNote ? "{$city}, {$state}" : ($city !== '' && ! $note ? "{$city}, {$state}" : ($state ?: null));

        return $note
            ? [...$entry, 'description' => $note['description'], 'scope' => $cityNote ? 'city' : 'state', 'place' => $place,
                'source_label' => $note['source_label'], 'source_url' => $note['source_url'], 'reviewed_at' => $note['reviewed_at']]
            : [...$entry, 'scope' => 'general', 'place' => $place, 'source_label' => null, 'source_url' => null, 'reviewed_at' => null];
    }

    /**
     * Sourced state and city notes grouped by office slug, for the glossary page.
     *
     * @return array<string, list<array{place: string, description: string, source_label: string, source_url: string, reviewed_at: string}>>
     */
    public static function placeNotes(): array
    {
        $notes = [];
        foreach (config('office_glossary.overrides', []) as $state => $entries) {
            foreach ($entries as $key => $note) {
                if ($key === 'cities') {
                    foreach ($note as $city => $cityNotes) {
                        foreach ($cityNotes as $slug => $cityNote) $notes[$slug][] = ['place' => "{$city}, {$state}", ...$cityNote];
                    }
                } else {
                    $notes[$key][] = ['place' => $state, ...$note];
                }
            }
        }
        foreach ($notes as &$list) usort($list, fn ($a, $b) => strcmp($a['place'], $b['place']));

        return $notes;
    }

    /** @return array{slug: string, title: string, description: string}|null */
    public static function for(?string $office): ?array
    {
        // Drop the seat identifier: "City Council Member District 3" is a council seat.
        $office = mb_strtolower(trim(preg_replace('/[\s,·—-]*\b(?:seat|ward|district)\s*#?\s*\d+\b.*$/i', '', (string) $office)));
        $slug = match (true) {
            (bool) preg_match('/^(?:u\.?s\.?|united states) (?:representative|house)/', $office) => 'us-representative',
            (bool) preg_match('/^(?:u\.?s\.?|united states) senat/', $office) => 'us-senator',
            $office === 'governor' => 'governor',
            in_array($office, ['lieutenant governor', 'lt. governor', 'lt governor'], true) => 'lieutenant-governor',
            $office === 'attorney general' => 'attorney-general',
            in_array($office, ['state treasurer', 'treasurer'], true) => 'state-treasurer',
            in_array($office, ['state controller', 'controller', 'comptroller'], true) => 'state-controller',
            $office === 'secretary of state' => 'secretary-of-state',
            $office === 'mayor' => 'mayor',
            (bool) preg_match('/^(?:city )?council ?(?:member|woman|man)?$/', $office) => 'city-council-member',
            (bool) preg_match('/^(?:superior|district|circuit|trial) court judge$/', $office) => 'trial-court-judge',
            default => null,
        };

        return $slug ? ['slug' => $slug, ...self::ENTRIES[$slug]] : null;
    }
}
