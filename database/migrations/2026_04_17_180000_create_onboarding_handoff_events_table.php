<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 24);
            $table->string('event_type', 24);
            $table->string('widget_key', 120);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['role', 'event_type', 'created_at'], 'onboarding_handoff_role_event_idx');
            $table->index(['user_id', 'created_at'], 'onboarding_handoff_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_handoff_events');
    }
};
