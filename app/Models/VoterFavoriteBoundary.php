<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A geographic boundary a voter has saved for quick access from the 3D map's
 * Layers → Boundaries panel. Either a congressional district (district_number
 * set) or a top city (city_name + lat/lng set).
 */
class VoterFavoriteBoundary extends Model
{
    public const TYPE_DISTRICT = 'district';
    public const TYPE_CITY     = 'city';

    protected $table = 'voter_favorite_boundaries';

    // The table only carries favorited_at (DB-defaulted to CURRENT_TIMESTAMP);
    // no created_at/updated_at columns.
    public $timestamps = false;

    protected $fillable = [
        'voter_id',
        'boundary_type',
        'state_abbr',
        'district_number',
        'city_name',
        'label',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat'           => 'float',
        'lng'           => 'float',
        'favorited_at'  => 'datetime',
    ];

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }
}