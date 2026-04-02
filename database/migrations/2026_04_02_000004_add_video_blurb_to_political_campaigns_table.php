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
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->text('video_blurb')
                ->nullable()
                ->after('message_summary')
                ->comment('Optional rich text blurb shown on voter ad watch page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('political_campaigns', function (Blueprint $table): void {
            $table->dropColumn('video_blurb');
        });
    }
};
