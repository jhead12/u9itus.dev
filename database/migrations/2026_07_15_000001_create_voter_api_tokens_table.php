<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-4: bearer-token credentials for stateless voter API consumers.
 *
 * The voter API (widget / mobile) is stateless — there is no session and voters
 * are not tied to a User account (voter.user_id is NULL for API-registered
 * voters by design). Instead, each voter is issued an opaque bearer token at
 * registration; the plaintext is shown once and the SHA-256 hash is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voter_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voter_id')->constrained('voters')->cascadeOnDelete();
            $table->string('name')->default('voter-api');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('voter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_api_tokens');
    }
};