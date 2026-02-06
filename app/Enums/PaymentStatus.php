<?php

namespace App\Enums;

/**
 * Payment status lifecycle for campaign billing.
 */
enum PaymentStatus: string
{
    case Pending    = 'pending';
    case Authorized = 'authorized';
    case Captured   = 'captured';
    case Refunded   = 'refunded';
}
