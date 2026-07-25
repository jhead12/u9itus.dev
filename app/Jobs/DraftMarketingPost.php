<?php

namespace App\Jobs;

use App\Services\Marketing\PostDraftingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Draft one PendingApproval blog Post for one politician from their freshest
 * un-drafted news/viral-moment source. The LLM call lives here (not inline in
 * the command) so a single timeout/failure can't abort the whole run.
 *
 * Mirrors DispatchCampaignChannel: ShouldQueue + four traits, tries=1,
 * timeout=300, primitive-only constructor, resolve-in-handle. ShouldBeUnique
 * keyed by politician_id prevents two concurrent jobs for the same politician
 * — the backstop behind PostDraftingService's source_hash dedup.
 */
class DraftMarketingPost implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /** Seconds to hold the uniqueness lock. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $politicianId,
        public readonly ?string $sourceFilter = null,
    ) {
    }

    public function uniqueId(): string
    {
        return (string) $this->politicianId;
    }

    public function handle(PostDraftingService $service): void
    {
        $result = $service->draftForPolitician($this->politicianId, $this->sourceFilter);

        Log::info('DraftMarketingPost: complete', array_merge($result, [
            'politician_id' => $this->politicianId,
        ]));
    }
}