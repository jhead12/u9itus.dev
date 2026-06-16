<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            // Indicates the politician won a primary and is on the general election
            // ballot but has not yet taken office. Set by the Ballotpedia import.
            $table->boolean('is_running_candidate')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('is_running_candidate');
        });
    }
};
