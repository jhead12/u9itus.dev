<?php

namespace App\Enums;

/**
 * Lifecycle of a registered marketing channel (first-party or third-party
 * plugin). Pending channels await admin approval before they appear in the
 * marketplace; disabled channels stay registered but are filtered out of
 * dispatch.
 */
enum ChannelStatus: string
{
    case Pending  = 'pending';
    case Active   = 'active';
    case Disabled = 'disabled';
}