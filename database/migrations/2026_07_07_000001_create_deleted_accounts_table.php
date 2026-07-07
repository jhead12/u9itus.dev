<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_accounts', function (Blueprint $table) {
            $table->id();

            // Original identifiers (no FK — the user row is gone)
            $table->unsignedBigInteger('original_user_id');
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('user_type')->nullable();
            $table->string('platform')->nullable();
            $table->string('registration_ip', 45)->nullable();

            // Original linked profile IDs (no FK — rows may be gone)
            $table->unsignedBigInteger('voter_id')->nullable();
            $table->unsignedBigInteger('politician_id')->nullable();
            $table->unsignedBigInteger('citizen_id')->nullable();

            // Full snapshot of the users row at time of deletion
            $table->json('user_snapshot');

            // Who deleted and why
            $table->unsignedBigInteger('deleted_by_user_id')->nullable(); // null = self-delete
            $table->text('deletion_reason')->nullable();
            $table->string('deleted_by_ip', 45)->nullable();

            // Restore tracking (Option B: new user_id on restore)
            $table->unsignedBigInteger('restored_user_id')->nullable(); // new user.id after restore
            $table->unsignedBigInteger('restored_by_user_id')->nullable();
            $table->timestamp('restored_at')->nullable();

            $table->timestamp('deleted_at'); // when the account was deleted
            $table->timestamps();

            $table->index('email');
            $table->index('original_user_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_accounts');
    }
};
