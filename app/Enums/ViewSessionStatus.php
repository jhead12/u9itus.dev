<?php

namespace App\Enums;

/**
 * Status lifecycle for a single view session.
 *
 * assigned → in_progress → completed
 *                        → expired
 *                        → flagged
 */
enum ViewSessionStatus: string
{
    case Assigned   = 'assigned';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Expired    = 'expired';
    case Flagged    = 'flagged';
}
