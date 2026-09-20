<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->string('website_url', 2048)->nullable();
            $table->string('website_source_url', 2048)->nullable();
            $table->timestamp('website_discovered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('committees', fn (Blueprint $table) => $table->dropColumn([
            'website_url', 'website_source_url', 'website_discovered_at',
        ]));
    }
};
