<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Represents a Wix site that has installed the Dial4Dough app.
 * Each Wix installation gets its own instance with OAuth tokens.
 */
class WixSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'site_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'site_display_name',
        'owner_email',
        'plan_type',
        'is_active',
        'installed_at',
        'uninstalled_at',
        'webhook_secret',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected $hidden = [
        'access_token',
        'refresh_token',
        'webhook_secret',
    ];

    /**
     * Politicians registered through this Wix site.
     */
    public function politicians()
    {
        return $this->hasMany(Politician::class);
    }

    /**
     * Voters registered through this Wix site.
     */
    public function voters()
    {
        return $this->hasMany(Voter::class);
    }

    /**
     * Check if the OAuth token has expired.
     */
    public function tokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
