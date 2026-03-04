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
        Schema::table('politicians', function (Blueprint $table) {
            // Profile verification status
            $table->enum('verification_status', ['unverified', 'pending', 'verified'])
                  ->default('unverified')
                  ->after('page_published');
            $table->string('verification_email')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_email');
            $table->string('verification_token')->nullable()->after('verified_at');
            
            // Public data source opt-in toggles (only available after verification)
            $table->boolean('show_ballotpedia_data')->default(false)->after('verification_token');
            $table->boolean('show_opensecrets_data')->default(false)->after('show_ballotpedia_data');
            $table->boolean('show_votesmart_data')->default(false)->after('show_opensecrets_data');
            $table->boolean('show_fec_data')->default(false)->after('show_votesmart_data');
            
            // External IDs for API lookups (stored after successful data fetch)
            $table->string('ballotpedia_id')->nullable()->after('show_fec_data');
            $table->string('opensecrets_id')->nullable()->after('ballotpedia_id');
            $table->string('votesmart_id')->nullable()->after('opensecrets_id');
            $table->string('fec_candidate_id')->nullable()->after('votesmart_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verification_email',
                'verified_at',
                'verification_token',
                'show_ballotpedia_data',
                'show_opensecrets_data',
                'show_votesmart_data',
                'show_fec_data',
                'ballotpedia_id',
                'opensecrets_id',
                'votesmart_id',
                'fec_candidate_id',
            ]);
        });
    }
};
