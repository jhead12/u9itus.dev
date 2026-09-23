<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politician_cleanup_run_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('step', 64);
            $table->string('scope', 16)->nullable(); // two-letter state code, or null = national
            $table->unsignedInteger('exit_code')->default(0);
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedInteger('auto_applied_count')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->json('breakdown')->nullable(); // per-reason counts, e.g. {"cross_state":2,"uncorroborated":5}
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['step', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_cleanup_run_metrics');
    }
};
