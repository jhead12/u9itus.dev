<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endorsements are detected from headlines by proximity of a title to a verb, which
 * cannot tell who endorsed whom ("Caucus endorses Escobar's Dignity Act" is support for
 * her bill; "...against Trump-backed..." is her opponent's endorsement). Only rows an
 * editor confirms are shown publicly.
 *
 * - status gains 'confirmed' (detected = awaiting review).
 * - kind: 'candidate' (endorsed the person) or 'bill' (endorsed their bill, bill_title).
 * - who reviewed it, when, and an optional note.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE politician_endorsements MODIFY COLUMN status ENUM('detected','confirmed','dismissed') NOT NULL DEFAULT 'detected'");
        } else {
            // SQLite/others emulate enum with a CHECK constraint; a plain string drops it.
            Schema::table('politician_endorsements', function (Blueprint $table) {
                $table->string('status', 16)->default('detected')->change();
            });
        }

        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->string('kind', 16)->default('candidate')->after('status');
            $table->string('bill_title', 255)->nullable()->after('kind');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('bill_title')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
            $table->index(['status', 'updated_at'], 'politician_endorsements_review_queue');
        });
    }

    public function down(): void
    {
        Schema::table('politician_endorsements', function (Blueprint $table) {
            $table->dropIndex('politician_endorsements_review_queue');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['kind', 'bill_title', 'reviewed_at', 'review_note']);
        });

        DB::table('politician_endorsements')->where('status', 'confirmed')->update(['status' => 'detected']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE politician_endorsements MODIFY COLUMN status ENUM('detected','dismissed') NOT NULL DEFAULT 'detected'");
        }
    }
};
