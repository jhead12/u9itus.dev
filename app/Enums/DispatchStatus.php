<?php

namespace App\Enums;

/**
 * Status of a single campaign_dispatches row — one recipient × one channel.
 *
 * queued      → row created, handoff to the channel pending
 * dispatched   → channel accepted the message (provider message id recorded)
 * delivered    → provider confirmed delivery (email opened / mail piece scanned /
 *                sms carrier-accepted — channel-specific)
 * bounced      → provider reported a hard bounce / undeliverable address
 * failed       → channel threw or rejected the payload (error_message set)
 * skipped      → pre-flight check rejected the recipient (e.g. outside territory,
 *                suppressed, no address on file) — not a delivery failure
 *
 * The dispatched/delivered/bounced transitions are the raw material for FEC
 * disbursement reporting and per-channel cost reconciliation, so they are
 * recorded as timestamped state, not logs.
 */
enum DispatchStatus: string
{
    case Queued     = 'queued';
    case Dispatched  = 'dispatched';
    case Delivered   = 'delivered';
    case Bounced     = 'bounced';
    case Failed      = 'failed';
    case Skipped     = 'skipped';
}