<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * campaign_channels — which channels a specific campaign has enabled.
 *
 * Polymorphic on the campaign so one abstraction serves both political_campaigns
 * and citizen_campaigns (and any future seller tier). Per-campaign channel
 * config (e.g. a custom from-name for email, or a mail piece template id for
 * direct mail) rides in `config`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_channels')) {
            return;
        }

        Schema::create('campaign_channels', function (Blueprint $table) {
            $table->id();

            $table->morphs('campaign');
            $table->foreignId('marketing_channel_id')
                ->constrained('marketing_channels')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();

            $table->timestamps();

            $table->unique(
                ['campaign_type', 'campaign_id', 'marketing_channel_id'],
                'campaign_channels_unique',
            );
            $table->index(['campaign_type', 'campaign_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_channels');
    }
};