<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\PoliticianSongPick;
use App\Services\MusicEmbedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated politician's CRUD for their "Favorite Songs" picks.
 *
 * All routes assume role:politician + onboarding middleware (registered
 * inside the politician.* route group). Voters and admins use separate
 * controllers — voters consume these via the public profile page.
 */
class PoliticianSongPickController extends Controller
{
    public function __construct(
        private readonly MusicEmbedService $embeds,
    ) {}

    /**
     * Show the management UI on the politician dashboard / profile editor.
     */
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $politician = $request->user()->politician;
        abort_unless($politician, 404);

        // Show inactive (soft-takedown) rows here so politicians know what
        // was removed and can appeal, but visually distinguish them.
        $picks = PoliticianSongPick::query()
            ->where('politician_id', $politician->id)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return view('standalone.politician.song-picks.index', [
            'politician' => $politician,
            'picks'      => $picks,
            'services'   => PoliticianSongPick::SERVICES,
        ]);
    }

    /**
     * Persist a new song pick. The URL is the only field that must
     * pass strict validation; metadata is optional and politician-editable.
     */
    public function store(Request $request): RedirectResponse
    {
        $politician = $request->user()->politician;
        abort_unless($politician, 404);

        $data = $request->validate([
            'track_url'   => ['required', 'string', 'max:2048'],
            'track_title' => ['nullable', 'string', 'max:200'],
            'artist_name' => ['nullable', 'string', 'max:200'],
            'note'        => ['nullable', 'string', 'max:280'],
            'is_explicit' => ['sometimes', 'boolean'],
        ]);

        $parsed = $this->embeds->validate($data['track_url']);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'track_url' => 'We only accept links from Spotify, Apple Music, or YouTube. Paste a track URL from one of those services.',
            ]);
        }

        // Compute next display_order so new picks land at the bottom.
        $maxOrder = (int) PoliticianSongPick::query()
            ->where('politician_id', $politician->id)
            ->max('display_order');

        try {
            PoliticianSongPick::create([
                'politician_id' => $politician->id,
                'service'       => $parsed['service'],
                'track_id'      => $parsed['track_id'],
                'track_url'     => $data['track_url'],
                'track_title'   => $data['track_title'] ?? null,
                'artist_name'   => $data['artist_name'] ?? null,
                'note'          => $data['note'] ?? null,
                'display_order' => $maxOrder + 1,
                'is_explicit'   => (bool) ($data['is_explicit'] ?? false),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'track_url' => 'You already added this track.',
            ]);
        }

        return redirect()
            ->route('politician.song-picks.index')
            ->with('status', 'Song added to your profile.');
    }

    /**
     * Remove a pick. We hard-delete instead of soft-delete because
     * is_active=false is reserved for takedown notices the admin team
     * issues — politician-initiated removal is a full delete.
     */
    public function destroy(Request $request, PoliticianSongPick $songPick): RedirectResponse
    {
        $politician = $request->user()->politician;
        abort_unless($politician, 404);
        abort_unless($songPick->politician_id === $politician->id, 403);

        $songPick->delete();

        return redirect()
            ->route('politician.song-picks.index')
            ->with('status', 'Song removed.');
    }

    /**
     * Reorder picks via drag-and-drop. Accepts an ordered array of pick IDs;
     * IDs not owned by the requesting politician are silently skipped.
     */
    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $politician = $request->user()->politician;
        abort_unless($politician, 404);

        $data = $request->validate([
            'order'   => ['required', 'array', 'min:1', 'max:50'],
            'order.*' => ['integer'],
        ]);

        foreach ($data['order'] as $index => $id) {
            PoliticianSongPick::query()
                ->where('id', $id)
                ->where('politician_id', $politician->id)
                ->update(['display_order' => $index]);
        }

        return response()->json(['ok' => true]);
    }
}
