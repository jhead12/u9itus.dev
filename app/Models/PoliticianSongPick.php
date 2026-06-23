<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One streaming-service track on a politician's "Favorite Songs" list.
 *
 * @property int    $id
 * @property int    $politician_id
 * @property string $service        spotify | apple | youtube
 * @property string $track_id
 * @property string $track_url
 * @property string|null $track_title
 * @property string|null $artist_name
 * @property string|null $note
 * @property int    $display_order
 * @property bool   $is_active
 * @property bool   $is_explicit
 */
class PoliticianSongPick extends Model
{
    public const SERVICE_SPOTIFY = 'spotify';
    public const SERVICE_APPLE   = 'apple';
    public const SERVICE_YOUTUBE = 'youtube';

    public const SERVICES = [
        self::SERVICE_SPOTIFY,
        self::SERVICE_APPLE,
        self::SERVICE_YOUTUBE,
    ];

    protected $fillable = [
        'politician_id',
        'service',
        'track_id',
        'track_url',
        'track_title',
        'artist_name',
        'note',
        'display_order',
        'is_active',
        'is_explicit',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'is_explicit'   => 'boolean',
        'display_order' => 'integer',
    ];

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    /**
     * Build the official embed URL for the streaming service.
     * Never reach this with un-validated user input — the controller
     * uses MusicEmbedService::validate() before persisting.
     */
    public function embedUrl(): ?string
    {
        return match ($this->service) {
            self::SERVICE_SPOTIFY => "https://open.spotify.com/embed/track/{$this->track_id}?utm_source=u9itus",
            self::SERVICE_APPLE   => "https://embed.music.apple.com/us/song/{$this->track_id}",
            self::SERVICE_YOUTUBE => "https://www.youtube-nocookie.com/embed/{$this->track_id}?rel=0&modestbranding=1",
            default               => null,
        };
    }

    /**
     * iframe `allow` attribute appropriate to the service. Each service
     * documents its required permissions; we use the minimum each needs.
     */
    public function embedAllow(): string
    {
        return match ($this->service) {
            self::SERVICE_SPOTIFY => 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
            self::SERVICE_APPLE   => 'autoplay *; encrypted-media *;',
            self::SERVICE_YOUTUBE => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
            default               => '',
        };
    }

    /**
     * Per-service recommended iframe height. Spotify compact = 80,
     * Apple compact = 175, YouTube 16:9 native ratio.
     */
    public function embedHeight(): int
    {
        return match ($this->service) {
            self::SERVICE_SPOTIFY => 80,
            self::SERVICE_APPLE   => 175,
            self::SERVICE_YOUTUBE => 200,
            default               => 100,
        };
    }
}
