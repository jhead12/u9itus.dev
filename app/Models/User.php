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
        'current_assignment_id',
        'is_available_for_assignment',
        'is_verified',
        'last_assignment_at',
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
            'last_assignment_at' => 'datetime',
            'password' => 'hashed',
            'is_available_for_assignment' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Get the advertiser profile for the user.
     */
    public function advertiser()
    {
        return $this->hasOne(Advertiser::class);
    }

    /**
     * Get the viewer profile for the user.
     */
    public function viewer()
    {
        return $this->hasOne(LoyaltyViewer::class);
    }

    /**
     * Get all ad assignments for this viewer.
     */
    public function assignments()
    {
        return $this->hasMany(AdAssignment::class, 'viewer_id');
    }

    /**
     * Get the current active assignment.
     */
    public function currentAssignment()
    {
        return $this->belongsTo(AdAssignment::class, 'current_assignment_id');
    }

    /**
     * Scope a query to only include advertisers.
     */
    public function scopeAdvertisers($query)
    {
        return $query->where('user_type', 'advertiser');
    }

    /**
     * Scope a query to only include viewers.
     */
    public function scopeViewers($query)
    {
        return $query->where('user_type', 'viewer');
    }

    /**
     * Scope a query to only include admins.
     */
    public function scopeAdmins($query)
    {
        return $query->where('user_type', 'admin');
    }
}
