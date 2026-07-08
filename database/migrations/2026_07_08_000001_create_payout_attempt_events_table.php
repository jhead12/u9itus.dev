<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_attempt_id')
                ->constrained('payout_attempts')
                ->cascadeOnDelete();
            $table->string('status', 20);      // pending|submitted|paid|failed|skipped
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('payout_attempt_id', 'pae_attempt_idx');
            $table->index('created_at', 'pae_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_attempt_events');
    }
};
