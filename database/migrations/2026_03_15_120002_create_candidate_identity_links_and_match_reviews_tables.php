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
        Schema::create('candidate_identity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_candidate_record_id')->constrained()->cascadeOnDelete();
            $table->decimal('match_score', 5, 4)->default(0);
            $table->string('link_source', 32)->default('system'); // system|admin
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('match_breakdown')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['politician_id', 'election_candidate_record_id'], 'candidate_identity_links_unique_pair');
            $table->index('match_score');
        });

        Schema::create('candidate_match_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('election_candidate_record_id')->constrained()->cascadeOnDelete();
            $table->decimal('match_score', 5, 4)->default(0);
            $table->json('match_breakdown')->nullable();
            $table->string('status', 32)->default('pending'); // pending|approved|rejected
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['politician_id', 'election_candidate_record_id'], 'candidate_match_reviews_unique_pair');
            $table->index('status');
            $table->index('match_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_match_reviews');
        Schema::dropIfExists('candidate_identity_links');
    }
};
