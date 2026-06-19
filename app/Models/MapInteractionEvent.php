<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Anonymous map interaction event.
 *
 * Stores every meaningful user action on the 3-D US map for UX analytics
 * and organic discovery research. No PII — IPs are hashed.
 *
 * @property int         $id
 * @property string      $session_id
 * @property string      $event_type
 * @property string|null $region
 * @property string|null $state
 * @property string|null $state_abbr
 * @property string|null $district
 * @property string|null $party
 * @property string|null $candidate_name
 * @property string|null $candidate_slug
 * @property array|null  $meta
 * @property string|null $ip_hash
 * @property string|null $user_agent_hash
 * @property string|null $referrer
 */
class MapInteractionEvent extends Model
{
    protected $table = 'map_interaction_events';

    protected $fillable = [
        'session_id',
        'event_type',
        'region',
        'state',
        'state_abbr',
        'district',
        'party',
        'candidate_name',
        'candidate_slug',
        'meta',
        'ip_hash',
        'user_agent_hash',
        'referrer',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
