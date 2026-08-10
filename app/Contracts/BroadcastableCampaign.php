<?php

namespace App\Contracts;

/**
 * Implemented by any campaign model whose approve/reject/stop/reactivate
 * lifecycle broadcasts to an owner's private Reverb channel.
 *
 * Lets App\Events\Campaign* and ReverbBroadcastService stay agnostic of
 * which concrete campaign type (political, citizen, ...) triggered them —
 * each implementer just says where its own events should be delivered.
 *
 * Implementers are expected to be Eloquent models exposing `id`, `uuid`,
 * and `title` attributes, since App\Events\Campaign* reads those directly.
 */
interface BroadcastableCampaign
{
    /**
     * The private channel name this campaign's lifecycle events broadcast
     * on, e.g. "politician.42" or "citizen.17".
     */
    public function broadcastChannelName(): string;
}
