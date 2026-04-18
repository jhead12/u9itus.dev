<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table): void {
            $table->string('reference_platform', 32)->nullable()->after('body')->index();
            $table->text('reference_url')->nullable()->after('reference_platform');
            $table->unsignedInteger('reference_start_seconds')->nullable()->after('reference_url');
            $table->unsignedInteger('reference_end_seconds')->nullable()->after('reference_start_seconds');
            $table->string('reference_note', 280)->nullable()->after('reference_end_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'reference_platform',
                'reference_url',
                'reference_start_seconds',
                'reference_end_seconds',
                'reference_note',
            ]);
        });
    }
};
