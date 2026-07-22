<?php

namespace App\Models;

use App\Enums\EventRsvpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's RSVP to a civic event.
 */
class EventRsvp extends Model
{
    use HasFactory;

    protected $table = 'event_rsvps';

    protected $fillable = [
        'civic_event_id',
        'user_id',
        'status',
        'guest_count',
        'notes',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRsvpStatus::class,
            'guest_count' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CivicEvent::class, 'civic_event_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventReminder::class, 'user_id', 'user_id')
            ->whereColumn('event_reminders.civic_event_id', 'event_rsvps.civic_event_id');
    }

    public function isAttending(): bool
    {
        return in_array($this->status?->value, [EventRsvpStatus::Yes->value, EventRsvpStatus::Approved->value], true);
    }

    public function isWaitlist(): bool
    {
        return $this->status === EventRsvpStatus::Waitlist;
    }
}
