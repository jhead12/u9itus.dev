<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\CivicEvent;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public endpoint returning geo-tagged civic content for the 3-D U.S. map.
 *
 * Surfaces published blog posts, upcoming civic events, and (opted-in)
 * citizen business locations.
 */
class MapContentController
{
    /** Maximum items returned per viewport query to keep payloads small. */
    private const MAX_ITEMS = 50;

    private const BUSINESS_CATEGORIES = ['food', 'retail', 'service', 'nonprofit', 'other'];

    /**
     * GET /api/v1/map/content
     *
     * Query params:
     *   south, west, north, east — bounding box (required)
     *   topic                    — optional politician_topics.slug filter
     *   category                 — optional Citizen::business_category filter
     *   limit                    — optional, capped at 50
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'south' => ['required', 'numeric', 'between:-90,90'],
            'west'  => ['required', 'numeric', 'between:-180,180'],
            'north' => ['required', 'numeric', 'between:-90,90', 'gte:south'],
            'east'  => ['required', 'numeric', 'between:-180,180'],
            'topic' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'in:'.implode(',', self::BUSINESS_CATEGORIES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ITEMS],
        ]);

        $south = (float) $data['south'];
        $west  = (float) $data['west'];
        $north = (float) $data['north'];
        $east  = (float) $data['east'];

        // Normalize longitude so a viewport spanning the antimeridian still works.
        if ($east < $west) {
            $east += 360;
        }

        $limit = min((int) ($data['limit'] ?? self::MAX_ITEMS), self::MAX_ITEMS);
        $topicSlug = ! empty($data['topic']) ? Str::slug($data['topic']) : null;
        $category = $data['category'] ?? null;

        $postQuery = Post::query()
            ->published()
            ->geoTagged()
            ->with(['author', 'topics'])
            ->orderByDesc('published_at')
            ->limit($limit);

        $eventQuery = CivicEvent::query()
            ->published()
            ->upcoming()
            ->geoTagged()
            ->with(['host.user', 'topics'])
            ->orderBy('starts_at')
            ->limit($limit);

        $businessQuery = Citizen::query()
            ->mappable()
            ->when($category, fn ($q) => $q->where('business_category', $category))
            ->orderByDesc('verified_at')
            ->limit($limit);

        foreach ([$postQuery, $eventQuery, $businessQuery] as $query) {
            if ($east > 180) {
                $query->where(function ($q) use ($west, $east): void {
                    $q->whereBetween('longitude', [$west, 180])
                      ->orWhereBetween('longitude', [-180, $east - 360]);
                })->whereBetween('latitude', [$south, $north]);
            } else {
                $query->withinBounds($south, $west, $north, $east);
            }
        }

        foreach ([$postQuery, $eventQuery] as $query) {
            if ($topicSlug) {
                $query->whereHas('topics', fn ($q) => $q->where('slug', $topicSlug));
            }
        }

        $posts  = $postQuery->get();
        $events = $eventQuery->get();
        $businesses = $businessQuery->get();

        return response()->json([
            'posts' => $posts->map(fn (Post $post) => [
                'id'           => $post->uuid,
                'type'         => 'post',
                'title'        => $post->title,
                'excerpt'      => $post->excerpt ?? Str::limit(strip_tags($post->body ?? ''), 140),
                'author_name'  => $post->author?->full_name ?? $post->author?->name ?? 'U9itus',
                'published_at' => $post->published_at?->toIso8601String(),
                'url'          => route('blog.show', $post),
                'lat'          => (float) $post->latitude,
                'lng'          => (float) $post->longitude,
                'location_name'=> $post->location_name,
                'is_promoted'  => $post->isPromoted(),
            ]),
            'events' => $events->map(fn (CivicEvent $event) => [
                'id'            => $event->uuid,
                'type'          => 'event',
                'title'         => $event->title,
                'excerpt'       => Str::limit(strip_tags($event->description ?? ''), 140),
                'host_name'     => $event->host?->public_name ?? $event->host?->organization_name ?? $event->host?->user?->name ?? 'U9itus',
                'event_type'    => $event->event_type->label(),
                'starts_at'     => $event->starts_at?->toIso8601String(),
                'url'           => route('events.show', $event),
                'lat'           => (float) $event->latitude,
                'lng'           => (float) $event->longitude,
                'location_name' => $event->location_name,
                'is_virtual'    => $event->is_virtual,
                'is_full'       => $event->isFull(),
            ]),
            'businesses' => $businesses->map(fn (Citizen $citizen) => [
                'id'                => $citizen->uuid,
                'type'              => 'business',
                'name'              => $citizen->business_name ?: $citizen->full_name,
                'category'          => $citizen->business_category,
                'address'           => collect([$citizen->address_line_1, $citizen->city, $citizen->state, $citizen->zip])
                    ->filter()
                    ->implode(', '),
                'lat'               => (float) $citizen->latitude,
                'lng'               => (float) $citizen->longitude,
                'verified'          => $citizen->isIdentityVerified(),
            ]),
            'total' => $posts->count() + $events->count() + $businesses->count(),
        ]);
    }
}
