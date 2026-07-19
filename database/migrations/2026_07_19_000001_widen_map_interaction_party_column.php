<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the party column on map_interaction_events so the endpoint
     * can accept full party names from the frontend before normalising
     * them to single-letter codes.
     */
    public function up(): void
    {
        Schema::table('map_interaction_events', function (Blueprint $table) {
            $table->string('party', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('map_interaction_events', function (Blueprint $table) {
            $table->string('party', 8)->nullable()->change();
        });
    }
};
