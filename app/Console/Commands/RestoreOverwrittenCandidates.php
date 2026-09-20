<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;

/**
 * One-shot data repair for candidate rows that `politicians:enrich-statewide`
 * used to rename into the sitting officeholder (e.g. the Gina Hinojosa row
 * turned into "Greg Abbott"). The upsert is fixed; this restores the rows it
 * already damaged.
 *
 * Usage (from Railway shell):
 *   php artisan politicians:restore-overwritten-candidates          (dry run)
 *   php artisan politicians:restore-overwritten-candidates --apply
 */
class RestoreOverwrittenCandidates extends Command
{
    protected $signature = 'politicians:restore-overwritten-candidates
        {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Restore candidate rows whose name/flags were overwritten by the statewide officeholder enricher.';

    /**
     * politician id => [expected slug, name to restore, party to restore (null = leave as is)].
     * The slug is the guard: a row is only touched when it still matches.
     */
    private const ROWS = [
        13943 => ['bf9bb-lieutenant-governor-mike-collier', 'Mike Collier', 'Democratic'],
        14521 => ['37e5e-lieutenant-governor-jason-richey', 'Jason Richey', null],
        14692 => ['0e4ad-governor-dusty-johnson', 'Dusty Johnson', 'Republican'],
        14802 => ['b6f7d-attorney-general-del-jon-cardin', 'Jon Cardin', null],
        18837 => ['758c8-governor-austin-gina-hinojosa', 'Gina Hinojosa', 'Democratic'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? '[LIVE — writing to DB]' : '[DRY RUN — pass --apply to write]');

        foreach (self::ROWS as $id => [$slug, $name, $party]) {
            $row = Politician::find($id);

            if (! $row || $row->slug !== $slug) {
                $this->warn("#{$id}: slug mismatch or missing — skipped");

                continue;
            }

            if ($row->verified_official) {
                $this->warn("#{$id}: verified official — skipped");

                continue;
            }

            $changes = [
                'full_name' => $name,
                'ballotpedia_id' => null,
                'term_status' => 'running',
                'is_running_candidate' => true,
                'profile_photo_url' => null,
                'profile_photo_status' => 'unvalidated',
            ];
            if ($party !== null) {
                $changes['party_affiliation'] = $party;
            }

            $this->line("#{$id} {$slug}: \"{$row->full_name}\" → \"{$name}\""
                .($party !== null ? " ({$party})" : '')
                .' | ballotpedia_id, photo cleared | running candidate');

            if ($apply) {
                $row->update($changes);
            }
        }

        return self::SUCCESS;
    }
}
