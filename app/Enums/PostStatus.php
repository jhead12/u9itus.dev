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
}
