<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A previously failed deploy attempt (varchar(1000) unique index over
        // InnoDB's 3072-byte key limit under utf8mb4) left production with a
        // bare `politician_chatter_items` table — CREATE TABLE had already
        // committed before the follow-up ADD UNIQUE statement errored — and
        // it has since taken a live row. Patch the existing table in place
        // instead of dropping it so that row survives.
        if (! Schema::hasTable('politician_chatter_items')) {
            Schema::create('politician_chatter_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
                $table->string('platform', 32);
                $table->string('source_url', 1000);
                // varchar(1000) is too wide for a plain unique index under utf8mb4
                // (1000 * 4 bytes exceeds InnoDB's 3072-byte key limit) — dedup on
                // a stored hash instead so the full URL is still kept intact.
                $table->string('source_url_hash', 64)->storedAs('SHA2(source_url, 256)');
                $table->string('source_author', 191)->nullable();
                $table->timestamp('source_published_at')->nullable();
                $table->json('engagement_metrics')->nullable();
                $table->string('headline', 240);
                $table->text('summary')->nullable();
                $table->string('claim_status', 24)->default('unverified');
                $table->string('moderation_status', 24)->default('pending');
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['politician_id', 'source_url_hash']);
                $table->index(['politician_id', 'moderation_status', 'published_at'], 'chatter_profile_publication_idx');
                $table->index(['moderation_status', 'created_at'], 'chatter_moderation_queue_idx');
            });
        } else {
            if (! Schema::hasColumn('politician_chatter_items', 'source_url_hash')) {
                Schema::table('politician_chatter_items', function (Blueprint $table) {
                    $table->string('source_url_hash', 64)->storedAs('SHA2(source_url, 256)')->after('source_url');
                });
            }
            $existingIndexes = collect(DB::select('SHOW INDEX FROM politician_chatter_items'))->pluck('Key_name')->unique();
            Schema::table('politician_chatter_items', function (Blueprint $table) use ($existingIndexes) {
                if (! $existingIndexes->contains('politician_chatter_items_politician_id_source_url_hash_unique')) {
                    $table->unique(['politician_id', 'source_url_hash']);
                }
                if (! $existingIndexes->contains('chatter_profile_publication_idx')) {
                    $table->index(['politician_id', 'moderation_status', 'published_at'], 'chatter_profile_publication_idx');
                }
                if (! $existingIndexes->contains('chatter_moderation_queue_idx')) {
                    $table->index(['moderation_status', 'created_at'], 'chatter_moderation_queue_idx');
                }
            });
        }

        if (! Schema::hasTable('politician_chatter_moderation_logs')) {
            Schema::create('politician_chatter_moderation_logs', function (Blueprint $table) {
                $table->id();
                // Explicit short constraint name: Laravel's auto-generated name
                // for this column ("politician_chatter_moderation_logs_..._foreign")
                // is 71 chars, over MySQL's 64-char identifier limit.
                $table->foreignId('politician_chatter_item_id');
                $table->foreign('politician_chatter_item_id', 'chatter_logs_item_id_foreign')
                    ->references('id')->on('politician_chatter_items')->cascadeOnDelete();
                $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 32);
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->json('changes')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['politician_chatter_item_id', 'created_at'], 'chatter_log_item_idx');
            });
        } else {
            // Same partial-apply pattern as politician_chatter_items above:
            // CREATE TABLE committed but the follow-up ALTER TABLE (FK or index)
            // failed, leaving the table bare.
            $existingKeys = collect(DB::select('SHOW INDEX FROM politician_chatter_moderation_logs'))->pluck('Key_name')->unique();
            $fkExists = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('TABLE_NAME', 'politician_chatter_moderation_logs')
                ->where('CONSTRAINT_NAME', 'chatter_logs_item_id_foreign')
                ->exists();
            Schema::table('politician_chatter_moderation_logs', function (Blueprint $table) use ($existingKeys, $fkExists) {
                if (! $fkExists) {
                    $table->foreign('politician_chatter_item_id', 'chatter_logs_item_id_foreign')
                        ->references('id')->on('politician_chatter_items')->cascadeOnDelete();
                }
                if (! $existingKeys->contains('chatter_log_item_idx')) {
                    $table->index(['politician_chatter_item_id', 'created_at'], 'chatter_log_item_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_chatter_moderation_logs');
        Schema::dropIfExists('politician_chatter_items');
    }
};
