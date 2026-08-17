<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_leads', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 32); // e.g. rss_google_news
            $table->string('full_name', 255);
            $table->string('state', 2)->nullable();
            $table->string('office_hint', 128)->nullable();
            $table->string('source_url', 2048);
            $table->char('source_hash', 64); // sha256(source_url), dedupe key
            $table->text('discovery_context')->nullable(); // raw RSS headline+snippet, for the LLM fallback tier
            $table->timestamp('discovered_at');
            $table->string('status', 24)->default('pending'); // pending|verified|rejected|promoted|auto_cleared
            $table->string('verifier_key', 32)->nullable(); // ballotpedia|wikipedia|llm_anthropic
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('verified_payload')->nullable();
            $table->foreignId('election_candidate_record_id')->nullable()
                ->constrained('election_candidate_records')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'source_hash']);
            $table->index(['status', 'discovered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_leads');
    }
};
