<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->text('wikipedia_url')->nullable()->after('website_url');
        });
    }

    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('wikipedia_url');
        });
    }
};
