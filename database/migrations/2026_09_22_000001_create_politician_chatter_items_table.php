<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('politician_chatter_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('source_url', 1000);
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

            $table->unique(['politician_id', 'source_url']);
            $table->index(['politician_id', 'moderation_status', 'published_at'], 'chatter_profile_publication_idx');
            $table->index(['moderation_status', 'created_at'], 'chatter_moderation_queue_idx');
        });

        Schema::create('politician_chatter_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_chatter_item_id')->constrained('politician_chatter_items')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('changes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['politician_chatter_item_id', 'created_at'], 'chatter_log_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_chatter_moderation_logs');
        Schema::dropIfExists('politician_chatter_items');
    }
};
