<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations (PACs, advocacy groups, unions, etc.) that can be linked to
 * a politician's donor snapshot via `pac_group_key` (see
 * config/pac_affiliations.php and App\Services\PacAffiliationClassifier).
 *
 * Claim/verification columns mirror the `politicians` table so an
 * org-claiming flow can be built later without another migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('org_type', 32)->default('pac');
            $table->string('logo_url', 512)->nullable();
            $table->string('website_url', 512)->nullable();
            $table->text('description')->nullable();

            // Matches a key in config('pac_affiliations.groups'). Intentionally
            // not a foreign key — pac_affiliations rows already written by
            // EnrichPoliticianDonors only ever store this string, never an org id,
            // so the join always happens at read time.
            $table->string('pac_group_key', 64)->nullable()->index();

            // Claimable/verifiable groundwork — mirrors politicians table.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('verification_status', ['unverified', 'pending', 'verified'])->default('unverified');
            $table->string('verification_email')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->string('claim_email')->nullable();
            $table->string('claim_token', 128)->nullable()->unique();
            $table->timestamp('claim_requested_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
