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
        'platform',
        'email_verified_at',
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

}
