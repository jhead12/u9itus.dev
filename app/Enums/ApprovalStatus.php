<?php

namespace App\Enums;

/**
 * Approval status for political campaigns (admin review gate).
 */
enum ApprovalStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
