<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * politician_song_picks
 *
 * Each row is one streaming-service track a politician has added to
 * their public "Favorite Songs" list. We store ONLY metadata — the
 * actual playback always happens inside the streaming service's
 * official embed widget. We never proxy, transcode, or store audio
 * bytes (which would require ASCAP/BMI/SESAC/SoundExchange licensing).
 *
 * Supported services (validated in MusicEmbedService):
 *   - spotify   (open.spotify.com/track/{id})
 *   - apple     (music.apple.com/.../song/.../{id})
 *   - youtube   (youtube.com/watch?v={id} or music.youtube.com/.../{id})
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('politician_song_picks', function (Blueprint $t) {
            $t->id();

            $t->foreignId('politician_id')
                ->constrained('politicians')
                ->cascadeOnDelete();

            // 'spotify' | 'apple' | 'youtube'
            $t->string('service', 16);

            // Service's track identifier — opaque string the embed needs.
            // Spotify: 22-char base62; Apple: 10-12 digit numeric;
            // YouTube: 11-char base64url. Max 64 covers all known formats.
            $t->string('track_id', 64);

            // Original full URL the politician pasted, kept for audit and
            // for clean cross-service re-validation later.
            $t->text('track_url');

            // Display title — politician-editable label, NOT the track's
            // real metadata (which we don't fetch to avoid scraping ToS issues).
            // Renders as "Why they picked it" subtitle on the profile.
            $t->string('track_title')->nullable();
            $t->string('artist_name')->nullable();

            // Optional politician commentary ("This song reminds me of…")
            // Capped to discourage long-form which belongs in the bio.
            $t->string('note', 280)->nullable();

            // 0-based display order; politicians can drag to reorder.
            $t->unsignedSmallInteger('display_order')->default(0);

            // Soft-takedown flag: when an artist or platform sends a
            // request to remove a track, we hide it without deleting
            // (preserves the politician's choice for an admin appeal).
            $t->boolean('is_active')->default(true);

            // For ADA + content-warning UI.
            $t->boolean('is_explicit')->default(false);

            $t->timestamps();

            // A politician can't add the same track twice on the same service.
            $t->unique(['politician_id', 'service', 'track_id'], 'pol_song_unique');

            // Speed the profile-page render: order picks by display_order.
            $t->index(['politician_id', 'is_active', 'display_order'], 'pol_song_render_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('politician_song_picks');
    }
};
