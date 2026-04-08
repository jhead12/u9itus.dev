<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table) {
            $table->string('subtitle_url')->nullable()
                ->after('media_url')
                ->comment('Optional WebVTT (.vtt) URL for video captions/subtitles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table) {
            $table->dropColumn('subtitle_url');
        });
    }
};
