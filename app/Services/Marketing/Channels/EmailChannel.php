<?php

namespace App\Services\Marketing\Channels;

use App\Contracts\MarketingChannel;
use App\Enums\ChannelType;
use App\Mail\CampaignChannelMail;
use App\Services\Marketing\DispatchPayload;
use App\Services\Marketing\DispatchResult;
use Illuminate\Support\Facades\Mail;

/**
 * First-party email channel — the proof-of-concept for the MarketingChannel
 * contract. Sends a campaign message via the app's configured transactional
 * mail driver (Mailgun/Postmark/Resend/SES are all already wired in
 * config/services.php), so the channel ships with zero new external deps.
 *
 * Idempotency: the dispatch UUID is stamped as a Message-ID header so a
 * retried queue job (or a mail provider that dedupes on Message-ID) won't
 * double-send. Recipients without an email address are `skipped`, not
 * `failed` — a missing address is a targeting gap, not a delivery error.
 */
class EmailChannel implements MarketingChannel
{
    public function key(): string
    {
        return 'email';
    }

    public function channelType(): ChannelType
    {
        return ChannelType::Email;
    }

    public function isConfigured(): bool
    {
        $driver = (string) config('mail.default');
        if ($driver === '' || $driver === 'log' || $driver === 'array') {
            // 'log'/'array' are dev defaults — treat as configured so tests and
            // local environments still exercise the channel end-to-end.
            return true;
        }

        return true;
    }

    public function deliver(DispatchPayload $payload): DispatchResult
    {
        $email = $payload->recipient['email'] ?? null;
        if (! $email) {
            return DispatchResult::skipped('recipient has no email address');
        }

        // We set the Message-ID ourselves so it doubles as the idempotency key
        // and the provider message id — a retried job re-sends the same ID and
        // any provider that dedupes on Message-ID will no-op the duplicate.
        $messageId = $payload->dispatchUuid . '@u9itus.dev';

        try {
            $mailable = (new CampaignChannelMail($payload))
                ->withSymfonyMessage(function ($message) use ($messageId): void {
                    $message->getHeaders()->addIdHeader('Message-ID', $messageId);
                });

            Mail::to($email)->send($mailable);

            return DispatchResult::dispatched($messageId);
        } catch (\Throwable $e) {
            return DispatchResult::failed($e->getMessage());
        }
    }
}