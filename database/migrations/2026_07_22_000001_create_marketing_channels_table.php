<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * marketing_channels — the channel/plugin registry.
 *
 * First-party channels (email today; direct-mail/sms later) are seeded here
 * with provider_class pointing at an in-repo App\Services\Marketing\Channels\*
 * implementation. Third-party marketplace plugins register the same shape with
 * is_first_party=false and a webhook endpoint in config, gated behind
 * ChannelStatus::Active (admin approval) so nothing dispatches pre-review.
 *
 * Polymorphic campaign linkage and per-recipient provenance live in the
 * companion campaign_channels / campaign_dispatches tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_channels')) {
            return;
        }

        Schema::create('marketing_channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Stable slug matched by ChannelRegistry and the channel's key().
            $table->string('key', 64)->unique();
            $table->string('label', 128);

            $table->string('channel_type', 32); // email|sms|direct_mail|digital_ad|door_knock|webhook

            // Implementing class (first-party) or webhook resolver (third-party).
            $table->string('provider_class', 255)->nullable();

            $table->boolean('is_first_party')->default(false);
            $table->string('status', 32)->default('active'); // pending|active|disabled

            // Provider credentials/defaults (API keys stored in env, NOT here —
            // this holds non-secret per-channel tunables + the future third-party
            // webhook url/secret reference).
            $table->json('config')->nullable();

            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_first_party']);
            $table->index('channel_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_channels');
    }
};