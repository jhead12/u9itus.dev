<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * reason holds a free-form LLM-generated explanation with no natural
     * length cap (e.g. "John Kerry is a real politician but represented
     * Massachusetts, not Louisiana..."). The varchar(255) limit was
     * routinely truncating these and crashing candidates:verify-leads with
     * SQLSTATE[22001], which in turn blocked every downstream step in the
     * sync-candidates workflow (including the election-results import).
     */
    public function up(): void
    {
        Schema::table('candidate_leads', function (Blueprint $table) {
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_leads', function (Blueprint $table) {
            $table->string('reason', 255)->nullable()->change();
        });
    }
};
