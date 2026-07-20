# Native Blog + Civic Events Implementation

**Branch:** `feature/native-blog-and-events` (to be merged after review)  
**Date:** 2026-07-19  
**Scope:** Phase 20 — native Laravel blogging system and a Partiful-style civic event layer.

---

## 1. What shipped

### Native Blog (Phase 20a)

- **Authored by citizens and politicians only.** Voters read, share, and comment but cannot author.
- **Full CRUD** with draft → pending approval → published → archived lifecycle.
- **SEO surface:** public index, topic archive, author archive, RSS (`/blog/feed`), sitemap entries, canonical URLs, Open Graph, Twitter cards, JSON-LD Article markup.
- **Geo-tagging:** posts carry `latitude`/`longitude`/`location_name` and appear as pins on the 3-D U.S. map.
- **Promotion via wallet credits:** citizens and politicians can promote posts for 1–30 days. Promotion deducts from `CitizenCredit`/`PoliticianCredit` ledgers and surfaces the post in:
  - a "Featured" slot on topic archive pages,
  - the "Latest from the community" panel in the voter ad-room.

### Civic Events (Phase 20b) — "the Partiful aspect"

- **Hosted by citizens and politicians.** Polymorphic `host_type`/`host_id` leaves room for neighborhood groups later.
- **Event types:** Town Hall, Ballot Measure Drive, Community Meeting, Rally, Workshop, Fundraiser.
- **Lifecycle:** Draft → Pending Approval → Published → Cancelled/Completed.
- **RSVP primitive:** yes / maybe / no / waitlist / approved / declined / pending.
- **Capacity + waitlist:** events can set a capacity; new RSVPs exceeding capacity are automatically waitlisted.
- **Approval mode:** hosts can require RSVP approval (`rsvp_requires_approval`).
- **Calendar export:** each event has an `.ics` download (`/events/{event}/ics`).
- **Public discovery:** `/events` index with search, location, and topic filters.
- **Map integration:** upcoming published geo-tagged events render alongside blog posts on the 3-D map.

---

## 2. Data model

```
posts
  uuid, slug, author_type, author_id, title, subtitle, excerpt, body,
  status, published_at, is_promoted, promoted_until, credit_spent,
  latitude, longitude, location_name, og_title, og_description, etc.

post_topic (pivot to politician_topics)

civic_events
  uuid, slug, host_type, host_id, event_type, status, title, description,
  location_name, venue_name, address, city, state, zip,
  latitude, longitude, starts_at, ends_at, timezone,
  capacity, rsvp_requires_approval, is_virtual, virtual_url,
  image_url, banner_url, goal_amount_cents, group_id, related_post_id

civic_event_topic (pivot to politician_topics)

event_rsvps
  civic_event_id, user_id, status, guest_count, notes, responded_at
```

All models use Eloquent factories and are covered by feature tests.

---

## 3. Routes

### Author routes (auth + role + onboarding/2FA)

- `citizen.posts.*` and `politician.posts.*` — blog CRUD, publish, archive, promote
- `citizen.events.*` and `politician.events.*` — event CRUD and cancellation

### Public routes

- `/blog`, `/blog/feed`, `/blog/topic/{slug}`, `/blog/author/{type}/{slug}`, `/blog/{slug}`
- `/events`, `/events/{event}`, `/events/{event}/rsvp`, `/events/{event}/ics`
- `/map` — 3-D map with civic content pins

### API

- `GET /api/v1/map/content` — public viewport query returning `posts` + `events`

---

## 4. Key services and controllers

| File | Responsibility |
|------|----------------|
| `app/Models/Post.php` | Polymorphic author, topics, slug generation, published/geo/promoted scopes |
| `app/Models/CivicEvent.php` | Polymorphic host, topics, RSVP helpers, capacity/waitlist logic |
| `app/Models/EventRsvp.php` | Status enum cast, attending/waitlist helpers |
| `app/Services/PostPromotionService.php` | Credit deduction + promotion flags |
| `app/Http/Controllers/Standalone/PostController.php` | Author blog CRUD + promotion action |
| `app/Http/Controllers/Standalone/PublicPostController.php` | Public blog + RSS |
| `app/Http/Controllers/Standalone/CivicEventController.php` | Author event CRUD |
| `app/Http/Controllers/Standalone/PublicCivicEventController.php` | Public events + ICS |
| `app/Http/Controllers/Standalone/EventRsvpController.php` | RSVP lifecycle |
| `app/Http/Controllers/Api/MapContentController.php` | Viewport content API |

---

## 5. Frontend / map layer

- `resources/js/map/ui/post-pins.js` now renders both blog posts (purple) and events (cyan) from the `/api/v1/map/content` response.
- `resources/css/map.css` adds `.post-pin-core.event` styling.
- `resources/views/standalone/public/us-map.blade.php` renamed the layer chip to **Civic Content**.

---

## 6. Tests

New test file: `tests/Feature/Blog/CivicEventTest.php`

- Citizen + politician event CRUD
- Cross-host authorization guard
- Cancellation
- Public browse, single event, ICS export
- RSVP yes / no / waitlist flow
- Map content API includes events

Existing blog tests continue to pass:

- `tests/Feature/Blog/PostCrudTest.php`
- `tests/Feature/Blog/PostPromotionTest.php`
- `tests/Feature/Blog/MapContentApiTest.php`

**Full regression suite:** 618 passed, 7 risky (no failures).

---

## 7. Deferred to future sprints

- **Neighborhood-group hosted events** (`group_id` column exists; integration deferred).
- **Event reminders / notifications** before start time.
- **Ticketed / paid events** tied to wallet credits.
- **Event check-in QR codes** and attendance reconciliation.

---

## 8. Merge notes

All changes live on `feature/native-blog-and-events`. Before merging to `master`:

1. Run migrations on the target environment:
   ```bash
   php artisan migrate
   ```
2. Build frontend assets:
   ```bash
   npm run build
   ```
3. Run the full test suite:
   ```bash
   php artisan test
   ```
4. Review the public map layer performance with real data loads.
