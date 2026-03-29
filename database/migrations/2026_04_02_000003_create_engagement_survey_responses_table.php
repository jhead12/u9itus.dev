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
        Schema::create('engagement_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('view_session_id')
                ->unique()  // One response per completed session
                ->constrained('view_sessions')
                ->cascadeOnDelete();
            $table->foreignId('campaign_id')
                ->constrained('political_campaigns')
                ->cascadeOnDelete();
            $table->foreignId('voter_id')
                ->constrained('voters')
                ->cascadeOnDelete();
            
            // Survey response payload: the voter's answer value
            $table->string('response_value', 255)
                ->comment('The selected option value or freeform response');
            
            $table->text('response_text')->nullable()
                ->comment('Full text of the response if needed');
            
            // Null-safety for legacy sessions without surveys
            $table->timestamp('responded_at')->nullable();
            
            $table->timestamps();
            
            // Composite index for admin reporting
            $table->index(['campaign_id', 'created_at']);
            $table->index(['voter_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagement_survey_responses');
    }
};
