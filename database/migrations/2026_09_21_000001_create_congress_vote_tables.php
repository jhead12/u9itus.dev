<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roll-call votes from the U.S. House Clerk and Senate.gov, plus how each member voted.
 * Members are keyed by Bioguide ID (politicians.bioguide_id) because both chambers'
 * feeds and the congress-legislators dataset all use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('politicians', 'bioguide_id')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->string('bioguide_id', 12)->nullable()->index();
            });
        }

        if (! Schema::hasTable('congress_votes')) {
            Schema::create('congress_votes', function (Blueprint $table) {
                $table->id();
                $table->string('chamber', 10);
                $table->unsignedSmallInteger('congress');
                $table->unsignedTinyInteger('session');
                $table->unsignedInteger('roll_number');
                $table->dateTime('voted_at')->nullable();
                $table->string('question', 255)->nullable();
                $table->text('title')->nullable();
                $table->string('bill_number', 60)->nullable();
                $table->string('result', 150)->nullable();
                $table->unsignedSmallInteger('yeas')->default(0);
                $table->unsignedSmallInteger('nays')->default(0);
                $table->unsignedSmallInteger('present')->default(0);
                $table->unsignedSmallInteger('not_voting')->default(0);
                // {"D": "yea", "R": "nay"}: the way each party's members split, for party-line stats.
                $table->json('party_positions')->nullable();
                $table->string('source_url', 500)->nullable();
                $table->timestamps();

                $table->unique(['chamber', 'congress', 'session', 'roll_number'], 'congress_votes_roll_unique');
                $table->index('voted_at');
            });
        }

        if (! Schema::hasTable('congress_member_votes')) {
            Schema::create('congress_member_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('congress_vote_id')->constrained('congress_votes')->cascadeOnDelete();
                $table->string('bioguide_id', 12);
                $table->string('party', 2)->nullable();
                // yea | nay | present | not_voting | other
                $table->string('vote', 12);

                $table->unique(['congress_vote_id', 'bioguide_id'], 'congress_member_votes_unique');
                $table->index(['bioguide_id', 'congress_vote_id'], 'congress_member_votes_member_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('congress_member_votes');
        Schema::dropIfExists('congress_votes');

        if (Schema::hasColumn('politicians', 'bioguide_id')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->dropColumn('bioguide_id');
            });
        }
    }
};
