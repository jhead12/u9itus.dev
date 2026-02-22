<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewUserNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  User  $newUser  The newly registered user.
     */
    public function __construct(public readonly User $newUser)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $role = ucfirst($this->newUser->user_type ?? 'user');

        return new Envelope(
            subject: "🔔 New {$role} Registered — " . ($this->newUser->name ?? $this->newUser->email),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.admin-new-user',
            textView: 'emails.admin-new-user-text',
        );
    }
}
