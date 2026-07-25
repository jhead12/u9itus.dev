<?php

namespace App\Enums;

enum EventRsvpStatus: string
{
    case Yes = 'yes';
    case Maybe = 'maybe';
    case No = 'no';
    case Waitlist = 'waitlist';
    case Approved = 'approved';
    case Declined = 'declined';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Yes => 'Going',
            self::Maybe => 'Maybe',
            self::No => 'Not going',
            self::Waitlist => 'Waitlist',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::Pending => 'Pending approval',
        };
    }
}
