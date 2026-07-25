<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProfileContactMethod extends Model
{
    protected $table = 'profile_contact_methods';

    protected $fillable = [
        'profilable_type',
        'profilable_id',
        'run_id',
        'kind',
        'value',
        'label',
        'country_code',
        'is_primary',
        'source_url',
        'source_selector',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
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

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }
}