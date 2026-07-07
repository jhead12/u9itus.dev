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
    ];

    protected $casts = [
        'user_snapshot'  => 'array',
        'deleted_at'     => 'datetime',
        'restored_at'    => 'datetime',
    ];

    public function isRestored(): bool
    {
        return $this->restored_at !== null;
    }
}
