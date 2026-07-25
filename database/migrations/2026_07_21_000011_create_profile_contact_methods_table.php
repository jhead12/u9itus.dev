<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_contact_methods
 *
 * Official phone / email / fax lines scraped from a politician's own official
 * or campaign website. Residential or personal numbers are never harvested —
 * only contact info published as official contact info.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        if (Schema::hasTable('profile_contact_methods')) {
            return;
        }

        Schema::create('profile_contact_methods', function (Blueprint $table) {
            $table->id();

            $table->morphs('profilable');

            $table->foreignId('run_id')
                ->nullable()
                ->constrained('profile_enrichment_runs')
                ->nullOnDelete();

            // phone | email | fax (offices still publish fax lines)
            $table->enum('kind', ['phone', 'email', 'fax']);

            $table->string('value', 255); // phone normalized, email lowercased
            $table->string('label', 128)->nullable(); // "District Office", "Capitol Office"
            $table->string('country_code', 2)->nullable();

            $table->boolean('is_primary')->default(false);

            $table->string('source_url', 2048);
            $table->string('source_selector', 255)->nullable(); // CSS-ish hint / nearest heading for audit

            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Dedup: one value per kind per profile.
            $table->unique(['profilable_type', 'profilable_id', 'kind', 'value'], 'profile_contact_methods_unique');
            $table->index(['profilable_type', 'profilable_id', 'kind'], 'profile_contact_methods_kind_idx');
            $table->index('is_verified', 'profile_contact_methods_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_contact_methods');
    }
};