<?php

namespace App\Models;

use App\Events\AdminSecurityAuditLogged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     *
     * The domain event is dispatched best-effort: a misbehaving listener
     * must never break the actual audit-write or the calling request.
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

        try {
            event(new AdminSecurityAuditLogged($log));
        } catch (\Throwable $e) {
            Log::error('AdminSecurityAuditLog listener failed', [
                'event' => $event,
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }

        return $log;
    }
}
