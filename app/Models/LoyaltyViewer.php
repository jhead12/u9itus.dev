<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoyaltyViewer extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'gender',
        'age_range',
        'preferred_cities',
        'preferred_states',
        'paypal_email',
        'cashapp_tag',
        'payment_method',
        'total_earned',
        'pending_earnings',
        'total_views',
        'trust_score',
    ];

    protected function casts(): array
    {
        return [
            'preferred_cities' => 'array',
            'preferred_states' => 'array',
            'total_earned' => 'decimal:2',
            'pending_earnings' => 'decimal:2',
            'trust_score' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the viewer profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all ad assignments for this viewer.
     */
    public function assignments()
    {
        return $this->hasMany(AdAssignment::class, 'viewer_id', 'user_id');
    }

    /**
     * Check if viewer is available for new assignment.
     */
    public function isAvailableForAssignment(): bool
    {
        return $this->user->is_available_for_assignment && 
               $this->user->is_verified &&
               $this->user->current_assignment_id === null;
    }

    /**
     * Get the current active assignment.
     */
    public function currentAssignment()
    {
        return $this->user->currentAssignment();
    }
}
