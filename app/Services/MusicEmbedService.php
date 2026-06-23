<?php

namespace App\Services;

use App\Models\PoliticianSongPick;

/**
 * MusicEmbedService
 *
 * Validates and normalises streaming-service track URLs for the
 * "Favorite Songs" feature. Returns a service + track_id tuple suitable
 * for the official embed widget.
 *
 * SECURITY: this is the only entry point that accepts user-supplied
 * URLs into the music feature. Anything not matching a known whitelist
 * regex is rejected — we never echo arbitrary URLs into <iframe src>.
 *
 * LEGAL: we never fetch, transcode, or store the audio bytes themselves.
 * Embeds defer all licensing/royalty obligations to the streaming
 * service. See doc/MUSIC_FEATURE.md (forthcoming) for the full rationale.
 */
class MusicEmbedService
{
    /**
     * Whitelist regexes. Each pattern's first capture group is the
     * canonical track ID. Order matters — more-specific patterns first.
     */
    private const PATTERNS = [
        // Spotify: open.spotify.com/track/{22-char base62}
        //          open.spotify.com/intl-XX/track/{id}
        PoliticianSongPick::SERVICE_SPOTIFY => [
            '#^https?://open\.spotify\.com/(?:intl-[a-z]{2}/)?track/([a-zA-Z0-9]{22})(?:[/?].*)?$#i',
        ],

        // Apple Music: music.apple.com/{country}/album/{slug}/{album-id}?i={track-id}
        //              The track id lives in the ?i= query param for individual songs.
        // YouTube Music URLs route through youtube.com so they hit the YouTube branch.
        PoliticianSongPick::SERVICE_APPLE => [
            '#^https?://music\.apple\.com/[a-z]{2}/album/[^/]+/(\d+)\?i=(\d+)#i',
        ],

        // YouTube: youtube.com/watch?v={11-char}
        //          youtu.be/{id}
        //          music.youtube.com/watch?v={id}
        PoliticianSongPick::SERVICE_YOUTUBE => [
            '#^https?://(?:www\.|music\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})#i',
            '#^https?://youtu\.be/([A-Za-z0-9_-]{11})#i',
        ],
    ];

    /**
     * Validate a user-supplied track URL.
     *
     * @return array{service: string, track_id: string}|null
     *         Tuple of (service, track_id) on success; null if no
     *         whitelisted service matched.
     */
    public function validate(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        foreach (self::PATTERNS as $service => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url, $m) === 1) {
                    // For Apple, the song id is the second capture
                    // (the first is the album id, which we don't need).
                    $trackId = $service === PoliticianSongPick::SERVICE_APPLE
                        ? ($m[2] ?? null)
                        : ($m[1] ?? null);

                    if ($trackId === null || $trackId === '') {
                        return null;
                    }

                    return [
                        'service'  => $service,
                        'track_id' => $trackId,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Best-effort service detection from a URL substring. Used for
     * inline UI hints ("we detected a Spotify link") without actually
     * persisting until validate() returns non-null.
     */
    public function guessService(string $url): ?string
    {
        return match (true) {
            str_contains($url, 'spotify.com')      => PoliticianSongPick::SERVICE_SPOTIFY,
            str_contains($url, 'music.apple.com')  => PoliticianSongPick::SERVICE_APPLE,
            str_contains($url, 'youtube.com'),
            str_contains($url, 'youtu.be')         => PoliticianSongPick::SERVICE_YOUTUBE,
            default                                => null,
        };
    }
}
