<?php

namespace App\Enums;

/**
 * Lifecycle status of a MarketingPostDraft provenance row.
 *
 * A draft row records every attempt the marketing content agent makes to turn
 * a news article / viral moment into a PendingApproval Post. `posted` means a
 * Post row was created for the source; `skipped` means the source was already
 * drafted (dedup) or the agent was not configured; `failed` means generation
 * or persistence threw and the error was captured here (never re-thrown).
 */
enum MarketingDraftStatus: string
{
    case Pending = 'pending'; // transient — set on create before the Post is saved
    case Posted   = 'posted';
    case Failed   = 'failed';
    case Skipped  = 'skipped';
}