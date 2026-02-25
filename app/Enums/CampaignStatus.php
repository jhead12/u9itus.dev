<?php

namespace App\Enums;

/**
 * Status lifecycle for political campaigns.
 *
 * draft → pending_approval → active → completed
 *                          ↘ scheduled → active   (Phase 14: future start date)
 *                          ↘ paused → active
 *                          ↘ cancelled
 */
enum CampaignStatus: string
{
    case Draft           = 'draft';
    case PendingApproval = 'pending_approval';
    case Scheduled       = 'scheduled';   // Approved but waiting for scheduled_start_at
    case Active          = 'active';
    case Paused          = 'paused';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';
}
