<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * neighborhood_groups — MVP slice of Sprint 8.5 (doc/Creative.md). Keys the
 * admin on users.id rather than Creative.md's literal admin_citizen_id,
 * since group creation is open to voters and citizens, not citizens only.
 * Funding (group_contributions/group_campaign_budget) and theme columns
 * are deferred — see doc/BELONGING_MEANING_IDENTITY_STRATEGY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhood_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();

            $table->foreignId('admin_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index(['state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhood_groups');
    }
};
