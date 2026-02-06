<?php

namespace App\Enums;

/**
 * Status lifecycle for political campaigns.
 *
 * draft → pending_approval → active → completed
 *                          ↘ paused → active
 *                          ↘ cancelled
 */
enum CampaignStatus: string
{
    case Draft           = 'draft';
    case PendingApproval = 'pending_approval';
    case Active          = 'active';
    case Paused          = 'paused';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';
}
