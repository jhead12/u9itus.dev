<?php

namespace App\Console\Commands;

use App\Jobs\DraftMarketingPost;
use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DraftMarketingPosts extends Command
{
    protected $signature = 'marketing:draft-posts
        {--limit=20        : Max politicians to process per run}
        {--politician=     : Process a single politician by ID or slug}
        {--source=         : Restrict source to news|viral}';

    protected $description = 'Draft human-reviewable blog Posts from recent news / viral moments for politicians (auto-drafts attributed to the politician; saved as PendingApproval).';

    public function handle(): int
    {
        if (!config('u9itus.marketing.enabled', true)) {
            $this->info('Marketing content agent disabled (u9itus.marketing.enabled).');
            return self::SUCCESS;
        }

        if (!config('u9itus.marketing.drafting.enabled', false)) {
            $this->info('Marketing drafting disabled (u9itus.marketing.drafting.enabled).');
            return self::SUCCESS;
        }

        $limit  = (int) $this->option('limit');
        $single = $this->option('politician');
        $source = $this->option('source');

        if ($source !== null && !in_array($source, ['news', 'viral'], true)) {
            $this->error('--source must be "news" or "viral".');
            return self::FAILURE;
        }

        $query = Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '');

        if ($single) {
            // No pre-filter by news/viral availability — the selector will
            // simply skip a single targeted politician with no fresh source.
            $query->where(fn ($q) =>
                $q->where('id', $single)->orWhere('slug', $single)
            );
        } else {
            $query->limit($limit);
        }

        $politicians = $query->get();

        if ($politicians->isEmpty()) {
            $this->info('No politicians to draft for.');
            return self::SUCCESS;
        }

        $this->info("Dispatching draft jobs for {$politicians->count()} politician(s)...");

        $dispatched = 0;
        $skipped = 0;

        foreach ($politicians as $politician) {
            try {
                DraftMarketingPost::dispatch($politician->id, $source);
                $dispatched++;
                $this->line("  → {$politician->full_name} (id={$politician->id})");
            } catch (\Throwable $e) {
                $skipped++;
                $this->warn("  ✗ {$politician->full_name}: {$e->getMessage()}");
                Log::warning('marketing:draft-posts dispatch failed', [
                    'politician_id' => $politician->id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Dispatched: {$dispatched} | Skipped: {$skipped}");

        return self::SUCCESS;
    }
}