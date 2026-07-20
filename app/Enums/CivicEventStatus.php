<?php

namespace App\Enums;

enum CivicEventStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }
}
