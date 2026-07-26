<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedAccount extends Model
{
    protected $fillable = [
        'original_user_id',
        'email',
        'first_name',
        'last_name',
        'user_type',
        'platform',
        'registration_ip',
        'voter_id',
        'politician_id',
        'citizen_id',
        'user_snapshot',
        'deleted_by_user_id',
        'deletion_reason',
        'deleted_by_ip',
        'restored_user_id',
        'restored_by_user_id',
        'restored_at',
        'deleted_at',
        'stripe_cleanup_plan',
        'stripe_cleanup_status',
        'stripe_cleanup_started_at',
        'stripe_cleanup_completed_at',
        'stripe_cleanup_error',
    ];

    protected $casts = [
        'user_snapshot'  => 'array',
        'deleted_at'     => 'datetime',
        'restored_at'    => 'datetime',
        'stripe_cleanup_plan' => 'array',
        'stripe_cleanup_started_at' => 'datetime',
        'stripe_cleanup_completed_at' => 'datetime',
    ];

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }
}
