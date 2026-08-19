<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * status_updated_at is stamped on every term_status sync (won, lost,
     * incumbent re-confirmation, still running) so it can't drive a
     * "recently won" badge on its own — a routine incumbent re-sync would
     * falsely look like a fresh win. won_at is set only when
     * ImportElectionResults records a 'won' result.
     */
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->timestamp('won_at')->nullable()->after('status_updated_at')
                ->comment('When this politician was last recorded as winning an election (won branch only)');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('won_at');
        });
    }
};
