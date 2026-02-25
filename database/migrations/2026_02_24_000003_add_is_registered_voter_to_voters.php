<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            // null  = not answered yet
            // true  = confirmed registered to vote
            // false = confirmed NOT registered (show registration link)
            $table->boolean('is_registered_voter')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropColumn('is_registered_voter');
        });
    }
};
