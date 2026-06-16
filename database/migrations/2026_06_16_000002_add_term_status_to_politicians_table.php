<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * term_status tracks the lifecycle of an unclaimed politician's seat:
     *
     *  seated   — currently in office (sourced from congress-legislators current feed)
     *  running  — won primary, on general ballot, not yet seated (from Ballotpedia)
     *  lost     — did not win most recent general election
     *  retired  — voluntarily left office / did not seek re-election
     *  unknown  — imported before this column existed or status not determinable
     */
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->string('term_status', 20)
                ->default('unknown')
                ->after('is_running_candidate')
                ->comment('seated | running | lost | retired | unknown');

            $table->date('term_ends_on')->nullable()->after('term_status')
                ->comment('Known term end date from congress-legislators feed');

            $table->timestamp('status_updated_at')->nullable()->after('term_ends_on')
                ->comment('When term_status was last set by an automated sync');

            $table->index(['term_status', 'is_active'], 'politicians_term_status_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropIndex('politicians_term_status_active_idx');
            $table->dropColumn(['term_status', 'term_ends_on', 'status_updated_at']);
        });
    }
};
