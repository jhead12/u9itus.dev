<?php

namespace App\Models;

use App\Events\AdminSecurityAuditLogged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class AdminSecurityAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'event',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Persist an admin security event and dispatch a domain event.
     */
    public static function record(User $admin, string $event, array $metadata = [], ?Request $request = null): self
    {
        $log = self::create([
            'admin_id' => $admin->id,
            'event' => $event,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        event(new AdminSecurityAuditLogged($log));

        return $log;
    }
}
