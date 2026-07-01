<?php

namespace App\Services;

/**
 * Flags contributor/committee names matching known advocacy-group/PAC
 * affiliations (e.g. AIPAC-aligned pro-Israel PACs) defined in
 * config/pac_affiliations.php.
 *
 * Source-agnostic: accepts any array of `{name, total}` entries, whether
 * scraped from OpenSecrets `top_contributors` or fetched directly from
 * FEC Schedule A committee-to-committee contributions. This is a derived,
 * read-only annotation layer — it does not scrape any additional source
 * (e.g. trackaipac.com), it only labels names already present in the
 * data passed to it.
 */
class PacAffiliationClassifier
{
    /**
     * Build the `pac_affiliations` array for a snapshot from its
     * top_contributors list.
     *
     * @param  array<int, array{name?: string, total?: mixed}>  $topContributors
     * @return array<int, array{group: string, label: string, matched_name: string, total: mixed}>
     */
    public function classify(array $topContributors): array
    {
        $groups = config('pac_affiliations.groups', []);
        if (empty($groups) || empty($topContributors)) {
            return [];
        }

        $matches = [];

        foreach ($topContributors as $contributor) {
            $name = trim((string) ($contributor['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $haystack = strtolower($name);

            foreach ($groups as $groupKey => $group) {
                foreach (($group['patterns'] ?? []) as $pattern) {
                    if (str_contains($haystack, strtolower($pattern))) {
                        $matches[] = [
                            'group'        => $groupKey,
                            'label'        => $group['label'] ?? $groupKey,
                            'matched_name' => $name,
                            'total'        => $contributor['total'] ?? null,
                        ];
                        continue 2; // stop checking other patterns for this group once matched
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * True if any contributor matched the given group key
     * (e.g. 'aipac_pro_israel').
     */
    public function hasGroup(array $pacAffiliations, string $groupKey): bool
    {
        foreach ($pacAffiliations as $match) {
            if (($match['group'] ?? null) === $groupKey) {
                return true;
            }
        }

        return false;
    }
}
