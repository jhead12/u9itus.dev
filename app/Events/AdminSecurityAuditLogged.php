<?php

namespace App\Events;

use App\Models\AdminSecurityAuditLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminSecurityAuditLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AdminSecurityAuditLog $auditLog,
    ) {}
}
