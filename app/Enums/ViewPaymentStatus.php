<?php

namespace App\Enums;

/**
 * Payout status for individual view sessions.
 */
enum ViewPaymentStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Paid     = 'paid';
    case Held     = 'held';
    case Rejected = 'rejected';
}
