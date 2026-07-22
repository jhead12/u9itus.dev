<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProfileAddress extends Model
{
    protected $table = 'profile_addresses';

    protected $fillable = [
        'profilable_type',
        'profilable_id',
        'run_id',
        'address_kind',
        'label',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'full_address',
        'lat',
        'lon',
        'source_url',
        'source_selector',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'lat'          => 'decimal:7',
            'lon'          => 'decimal:7',
        ];
    }

    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ProfileEnrichmentRun::class, 'run_id');
    }

    public function scopeOffice(Builder $query): Builder
    {
        return $query->where('address_kind', 'office');
    }

    public function scopeDistrict(Builder $query): Builder
    {
        return $query->where('address_kind', 'district');
    }

    public function scopeMailing(Builder $query): Builder
    {
        return $query->where('address_kind', 'mailing');
    }

    /** Human-readable one-liner for display. */
    public function toDisplayString(): string
    {
        $parts = array_filter([
            $this->line1,
            $this->line2,
            $this->city,
            $this->state,
            $this->postal_code,
        ], fn ($v) => !empty($v) && is_string($v) && trim($v) !== '');

        return implode(', ', $parts);
    }
}