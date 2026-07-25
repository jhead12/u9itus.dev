<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * marketing_post_drafts — provenance + dedup for the marketing content agent.
 *
 * Each row records one attempt by PostDraftingService to turn a recent news
 * article or viral moment into a PendingApproval Post attributed to the
 * politician the material is about. `source_hash` (sha256 of
 * `{politician_id}|{source_type}|{source_id}`) is UNIQUE so a re-run for the
 * same source is a no-op — the agent picks the next un-drafted source instead.
 *
 * `post_id` is nullable so a `failed` row (generation/persistence threw) still
 * has a provenance record even though no Post was created; nullOnDelete keeps
 * the draft row if a human later deletes the generated Post.
 *
 * source_type / source_id deliberately do NOT use a polymorphic morphMap (none
 * is registered in this repo, and a MorphTo would persist full class names);
 * PostDraftingService::resolveSource() switches on the source_type enum
 * instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_post_drafts')) {
            return;
        }

        Schema::create('marketing_post_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();

            // news|viral_moment — see App\Enums\MarketingSourceType.
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');

            // sha256("{politician_id}|{source_type}|{source_id}") — the dedup key.
            $table->string('source_hash', 64)->unique();

            $table->string('source_url')->nullable();
            $table->string('source_title')->nullable();

            // Nullable so a `failed` draft row (no copy was generated) can
            // still record provenance. `posted` rows always populate these.
            $table->string('generated_title')->nullable();
            $table->string('generated_excerpt')->nullable();
            $table->longText('generated_body')->nullable();
            $table->string('featured_image_url')->nullable();

            // pending|posted|failed|skipped — see App\Enums\MarketingDraftStatus.
            $table->string('status', 20)->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->index(['politician_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_post_drafts');
    }
};