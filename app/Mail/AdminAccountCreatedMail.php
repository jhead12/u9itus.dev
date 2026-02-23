<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  User    $admin      The newly created/updated admin user.
     * @param  bool    $isNew      True when the account was just created; false when updated.
     * @param  string  $tempPass   Raw password (only passed when freshly created).
     */
    public function __construct(
        public readonly User $admin,
        public readonly bool $isNew = true,
        public readonly string $tempPass = ''
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $action = $this->isNew ? 'Created' : 'Updated';

        return new Envelope(
            subject: "🔐 Admin Account {$action} — " . config('app.name', 'U9itus'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-account-created',
            text: 'emails.admin-account-created-text',
        );
    }
}
