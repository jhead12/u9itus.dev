<?php

namespace App\Mail;

use App\Models\ImportRunLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaliforniaImportSyncFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ImportRunLog $runLog
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[U9itus] California Import Sync Failed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.california-import-sync-failed',
            text: 'emails.california-import-sync-failed-text',
        );
    }
}
