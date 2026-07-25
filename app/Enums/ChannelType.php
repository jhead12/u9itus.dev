<?php

namespace App\Enums;

/**
 * The kind of delivery channel a marketing plugin implements.
 *
 * Only `Email` is implemented as a first-party channel today; the remaining
 * values are forward-compatible enum slots for the marketplace — `DirectMail`
 * (skiptrace → address verify → API mailer + geofenced QR landing flow),
 * `Sms`, `DigitalAd`, `DoorKnock`, and `Webhook` (third-party plugins) all
 * ship against the same App\Contracts\MarketingChannel contract.
 */
enum ChannelType: string
{
    case Email      = 'email';
    case Sms        = 'sms';
    case DirectMail = 'direct_mail';
    case DigitalAd  = 'digital_ad';
    case DoorKnock  = 'door_knock';
    case Webhook    = 'webhook';
}