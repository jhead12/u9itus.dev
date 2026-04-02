<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source',
        'ip_address',
        'user_agent',
        'is_mobile',
        'is_vpn_suspected',
        'vpn_signal',
        'request_path',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_mobile' => 'boolean',
            'is_vpn_suspected' => 'boolean',
            'accessed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
