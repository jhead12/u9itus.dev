<?php

namespace App\Enums;

/**
 * Lifecycle status for native blog posts.
 */
enum PostStatus: string
{
    case Draft           = 'draft';
    case PendingApproval = 'pending_approval';
    case Published       = 'published';
    case Archived        = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft           => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Published       => 'Published',
            self::Archived        => 'Archived',
        };
    }
}
