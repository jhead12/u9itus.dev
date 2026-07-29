<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * group_memberships — keyed on user_id (not voter_id/citizen_id like
 * voter_favorite_causes) since a group can have both voter and citizen
 * members. No contribution_tier column — Patreon-style funding is deferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('neighborhood_group_id')
                ->constrained('neighborhood_groups')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role')->default('member');
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['neighborhood_group_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
