<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table) {
            $table->string('public_visibility', 20)
                ->default('pending')
                ->after('status')
                ->index();
            $table->boolean('is_public_board')
                ->default(false)
                ->after('public_visibility')
                ->index();
            $table->string('public_alias', 40)
                ->nullable()
                ->after('is_public_board');
            $table->text('campaign_reply')
                ->nullable()
                ->after('admin_notes');
            $table->foreignId('campaign_replied_by')
                ->nullable()
                ->after('campaign_reply')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('campaign_replied_at')
                ->nullable()
                ->after('campaign_replied_by')
                ->index();
            $table->foreignId('published_by')
                ->nullable()
                ->after('campaign_replied_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('published_at')
                ->nullable()
                ->after('published_by')
                ->index();
        });

        DB::table('voter_watch_reports')
            ->where('type', 'message')
            ->where('status', 'resolved')
            ->whereNotNull('admin_notes')
            ->update([
                'public_visibility' => 'approved',
                'is_public_board' => true,
                'published_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('voter_watch_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn('published_at');
            $table->dropConstrainedForeignId('campaign_replied_by');
            $table->dropColumn('campaign_replied_at');
            $table->dropColumn('campaign_reply');
            $table->dropColumn('public_alias');
            $table->dropColumn('is_public_board');
            $table->dropColumn('public_visibility');
        });
    }
};
