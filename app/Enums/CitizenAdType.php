<?php

namespace App\Enums;

/**
 * Citizen campaign ad type — determines pricing tier and approval routing.
 *
 * BallotIssue campaigns always route to the admin approval queue (regardless
 * of the citizen's Stripe verification status) and require pac_registration_id.
 * All other types auto-approve once the citizen's identity is verified.
 */
enum CitizenAdType: string
{
    case LocalBusiness       = 'local_business';
    case CommunityNotice     = 'community_notice';
    case BallotIssue         = 'ballot_issue';
    case GeneralAnnouncement = 'general_announcement';
}
