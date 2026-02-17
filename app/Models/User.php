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
        'user_type',
        'first_name',
        'last_name',
        'phone',
        'phone_verified_at',
        'kyc_status',
        'street_address',
        'city',
        'state',
        'zip_code',
        'country',
        'is_verified',
        'wix_member_id',
        'wix_instance_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_verified'       => 'boolean',
        ];
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
     * Wix site linked to this user via instance ID.
     */
    public function wixSite(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WixSite::class, 'wix_instance_id', 'instance_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    /**
     * Scope to admin users.
     */
    public function scopeAdmins($query): void
    {
        $query->where('user_type', 'admin');
    }

    /**
     * Whether this user was created via Wix SSO (has no password).
     */
    public function isWixSsoUser(): bool
    {
        return !empty($this->wix_member_id);
    }
}
