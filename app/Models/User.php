<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_two_factor_secret',
        'admin_two_factor_confirmed_at',
        'admin_two_factor_recovery_codes',
        'user_type',
        'first_name',
        'last_name',
        'phone',
        'phone_verified_at',
        'kyc_status',
        'kyc_reviewed_at',
        'kyc_reviewer_id',
        'kyc_rejection_reason',
        'kyc_document_path',
        'street_address',
        'city',
        'state',
        'zip_code',
        'country',
        'is_verified',
        'platform',
        'email_verified_at',
        'suspended_at',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'admin_two_factor_secret',
        'admin_two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'phone_verified_at'  => 'datetime',
            'kyc_reviewed_at'    => 'datetime',
            'suspended_at'       => 'datetime',
            'admin_two_factor_confirmed_at' => 'datetime',
            'admin_two_factor_secret' => 'encrypted',
            'admin_two_factor_recovery_codes' => 'encrypted:array',
            'password'           => 'hashed',
            'is_verified'        => 'boolean',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * Get the user's full name by combining first and last name.
     * Falls back to the name column if first_name/last_name are not set.
     */
    public function getNameAttribute($value): string
    {
        // If name column has a value, use it (legacy support)
        if ($value) {
            return $value;
        }

        // Otherwise, combine first_name and last_name
        $parts = array_filter([
            $this->attributes['first_name'] ?? null,
            $this->attributes['last_name'] ?? null
        ]);

        return implode(' ', $parts);
    }

    /**
     * Set the user's name by splitting into first and last name.
     * This allows backward compatibility with code that sets 'name' directly.
     */
    public function setNameAttribute($value): void
    {
        // Split the name into parts
        $parts = explode(' ', trim($value), 2);
        
        $this->attributes['first_name'] = $parts[0] ?? '';
        $this->attributes['last_name'] = $parts[1] ?? '';
        
        // Keep the name column in sync for backward compatibility
        $this->attributes['name'] = $value;
    }

    /**
     * Whether this user account is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Whether this user's KYC is pending review.
     */
    public function isKycPending(): bool
    {
        return $this->kyc_status === 'pending';
    }

    // ── Political platform relationships ─────────────────────

    /**
     * Politician profile linked to this user.
     */
    public function politician(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Politician::class);
    }

    /**
     * Voter profile linked to this user.
     */
    public function voter(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Voter::class);
    }

    /**
     * Notification preferences for this user.
     */
    public function notificationPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Valid user_type values for the standalone political platform.
     */
    public const ROLES = ['admin', 'politician', 'voter'];

    /**
     * Scope to admin users.
     */
    public function scopeAdmins($query): void
    {
        $query->where('user_type', 'admin');
    }

    /**
     * Scope to politician users.
     */
    public function scopePoliticians($query): void
    {
        $query->where('user_type', 'politician');
    }

    /**
     * Scope to voter users.
     */
    public function scopeVoters($query): void
    {
        $query->where('user_type', 'voter');
    }

    /**
     * Determine whether the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->user_type === 'admin';
    }

    /**
     * Determine whether admin two-factor is configured and confirmed.
     */
    public function hasAdminTwoFactorEnabled(): bool
    {
        return !empty($this->admin_two_factor_secret) && $this->admin_two_factor_confirmed_at !== null;
    }

}
