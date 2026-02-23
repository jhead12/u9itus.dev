<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Audit Logs
 *
 * Tracks every admin action taken on a campaign:
 *   edited       – field-level changes recorded in JSON
 *   stopped      – admin forced the campaign to paused state
 *   reactivated  – admin re-enabled a stopped/paused campaign
 *   approved     – campaign approved
 *   rejected     – campaign rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('political_campaigns')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 50);            // edited | stopped | reactivated | approved | rejected
            $table->text('reason')->nullable();       // admin-supplied reason for stop / reject
            $table->json('changes')->nullable();      // { field: { old: x, new: y } }
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_audit_logs');
    }
};
