<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * profile_addresses
 *
 * Official / district / mailing addresses scraped from a politician's own
 * official or campaign website.
 *
 * SAFETY: address_kind is an enum of office|district|mailing only — there is
 * no residential value, and the service layer's normalizeAddress() rejects any
 * candidate whose nearby label text looks residential ("home", "residence",
 * "private address", …). There is no legal path for a residential address to
 * land in this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Self-heal: skip if a prior partial batch already created the table
        // (see profile_enrichment_runs migration for the MySQL DDL rationale).
        // This is the table that surfaced SQLSTATE 42S01/1050 in production.
        if (Schema::hasTable('profile_addresses')) {
            return;
        }

        Schema::create('profile_addresses', function (Blueprint $table) {
            $table->id();

            $table->morphs('profilable');

            $table->foreignId('run_id')
                ->nullable()
                ->constrained('profile_enrichment_runs')
                ->nullOnDelete();

            // office | district | mailing — never residential.
            $table->enum('address_kind', ['office', 'district', 'mailing']);

            $table->string('label', 128)->nullable(); // "Capitol Office", "District Office"
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 128);
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 2)->default('US');

            // Joined string for display / dedup.
            $table->string('full_address', 512);

            // Optional geocoding (not populated by v1).
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 11, 7)->nullable();

            $table->string('source_url', 2048);
            $table->string('source_selector', 255)->nullable();

            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->unique(['profilable_type', 'profilable_id', 'address_kind', 'full_address'], 'profile_addresses_unique');
            $table->index(['profilable_type', 'profilable_id', 'address_kind'], 'profile_addresses_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_addresses');
    }
};