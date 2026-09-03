<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per state that a search hit with no ballot-measure data. Drives the
 * on-demand backfill: a zero-result search debounce-dispatches
 * BackfillStateElectionData, which records its outcome here so the next search
 * can say "still gathering" / "nothing published yet" without re-running, and
 * so watchers can be emailed when the state flips to `ready`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_data_backfills', function (Blueprint $table) {
            $table->id();
            $table->char('state', 2)->unique();
            $table->string('status', 16)->default('queued'); // queued|running|ready|unavailable|failed
            $table->unsignedInteger('measures_found')->default(0);
            $table->unsignedInteger('elections_found')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->json('watch_emails')->nullable(); // [{email, requested_at}]
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_data_backfills');
    }
};
