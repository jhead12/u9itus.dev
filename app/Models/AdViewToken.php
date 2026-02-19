<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Secure one-time use token for ad viewing via email/SMS notifications.
 * 
 * Prevents fraud by:
 * - One-time use only
 * - Expiration time (default 24 hours)
 * - IP/device tracking
 * - Rate limiting per voter
 * - Audit trail of all notifications
 */
class AdViewToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'political_campaign_id',
        'voter_id',
        'notification_method',      // email | sms | both
        'sent_to',                  // email address or phone number
        'sent_at',
        'expires_at',
        'viewed_at',
        'view_session_id',
        'ip_address_used',
        'device_fingerprint_used',
        'is_used',
        'is_expired',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'viewed_at' => 'datetime',
        'is_used' => 'boolean',
        'is_expired' => 'boolean',
    ];

    protected $hidden = [
        'token',
        'ip_address_used',
        'device_fingerprint_used',
    ];

    /**
     * Boot method to generate token automatically
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($token) {
            if (empty($token->token)) {
                $token->token = self::generateSecureToken();
            }
            if (empty($token->expires_at)) {
                $token->expires_at = Carbon::now()->addHours(24);
            }
        });
    }

    /**
     * Generate a cryptographically secure token
     */
    public static function generateSecureToken(): string
    {
        return hash('sha256', Str::random(64) . time() . random_bytes(32));
    }

    /**
     * Check if token is valid for use
     */
    public function isValid(): bool
    {
        return !$this->is_used 
            && !$this->is_expired 
            && $this->expires_at->isFuture();
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(string $ipAddress, ?string $deviceFingerprint = null): void
    {
        $this->update([
            'is_used' => true,
            'viewed_at' => now(),
            'ip_address_used' => $ipAddress,
            'device_fingerprint_used' => $deviceFingerprint,
        ]);
    }

    /**
     * Check if token has expired
     */
    public function checkExpiration(): void
    {
        if ($this->expires_at->isPast() && !$this->is_expired) {
            $this->update(['is_expired' => true]);
        }
    }

    /**
     * Generate viewing URL
     */
    public function getViewingUrl(): string
    {
        return route('voter.watch', ['token' => $this->token]);
    }

    // Relationships

    public function campaign()
    {
        return $this->belongsTo(PoliticalCampaign::class, 'political_campaign_id');
    }

    public function voter()
    {
        return $this->belongsTo(Voter::class);
    }

    public function viewSession()
    {
        return $this->belongsTo(ViewSession::class);
    }
}
