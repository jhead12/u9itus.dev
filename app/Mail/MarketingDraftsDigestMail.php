<?php

namespace App\Mail;

use App\Models\MarketingPostDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Daily digest sent to platform admins listing the marketing content agent's
 * new PendingApproval blog drafts.
 *
 * Each draft is attributed to a politician (the post author), generated from a
 * news article or viral moment, and held in PostStatus::PendingApproval until
 * the politician reviews + publishes it from their dashboard. The digest just
 * surfaces what's waiting — it does not publish or approve anything. Links go
 * to the politician's public profile (there is no admin post-edit route; the
 * politician is the reviewer).
 */
class MarketingDraftsDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, MarketingPostDraft>  $drafts
     */
    public function __construct(
        public readonly Collection $drafts,
    ) {
    }

    public function envelope(): Envelope
    {
        $count = $this->drafts->count();
        $noun = $count === 1 ? 'draft' : 'drafts';

        return new Envelope(
            subject: "{$count} new marketing {$noun} awaiting review",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.marketing-drafts-digest',
            text: 'emails.marketing-drafts-digest-text',
        );
    }
}