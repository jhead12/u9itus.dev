<?php

namespace App\Enums;

/**
 * Campaign content type — video message or live feed.
 */
enum CampaignType: string
{
    case Video    = 'video';
    case LiveFeed = 'live_feed';
    case QAndA    = 'q_and_a';
}
