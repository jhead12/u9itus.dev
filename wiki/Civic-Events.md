# Civic Events

U9itus includes a native Laravel civic-event layer (Phase 20 and follow-on notification work) for town halls, ballot-measure drives, rallies, workshops, fundraisers, and community meetings. Citizens and politicians can create events; voters and other users can RSVP, receive reminders, and add events to their calendars.

## Contents

- [Overview](#overview)
- [Data model](#data-model)
- [Event lifecycle](#event-lifecycle)
- [RSVP lifecycle](#rsvp-lifecycle)
- [Notifications](#notifications)
- [Scheduler](#scheduler)
- [Admin-editable email templates](#admin-editable-email-templates)
- [Routes](#routes)
- [Tests](#tests)
- [Key files](#key-files)

## Overview

Events are public, geo-tagged, and surfaced on the interactive U.S. map alongside promoted blog posts. They support:

- Polymorphic hosts (`Citizen`, `Politician`; extensible to `NeighborhoodGroup`)
- Capacity limits and automatic waitlist
- Optional host approval mode
- `.ics` calendar export
- Related blog posts
- Topic tagging
- A complete notification lifecycle for hosts and attendees

## Data model

### `civic_events`

| Column | Purpose |
|--------|---------|
| `uuid` | Public identifier used by map API |
| `slug` | SEO-friendly route key |
| `host_type` / `host_id` | Polymorphic host |
| `event_type` | Enum: town hall, rally, workshop, fundraiser, ballot measure drive, community meeting |
| `status` | `published`, `pending_approval`, `cancelled`, `completed` |
| `title`, `description` | Event content |
| `location_name`, `venue_name`, `address`, `city`, `state`, `zip` | Human location |
| `latitude`, `longitude` | Map pin |
| `starts_at`, `ends_at`, `timezone` | Event timing |
| `capacity` | Max attendees; null means unlimited |
| `rsvp_requires_approval` | Pending → Approved/Declined flow |
| `is_virtual`, `virtual_url` | Online events |
| `image_url`, `banner_url` | Visuals |
| `goal_amount_cents` | Fundraiser target (reserved) |
| `related_post_id` | Linked native blog post |

### `event_rsvps`

| Column | Purpose |
|--------|---------|
| `civic_event_id` | Event |
| `user_id` | Attendee |
| `status` | `yes`, `maybe`, `no`, `waitlist`, `approved`, `declined`, `pending` |
| `guest_count` | Total guests including the attendee |
| `notes` | Optional note to the host |
| `responded_at` | Last response timestamp |

### `event_reminders`

Deduplication table tracking which attendee reminders have already been sent.

| Column | Purpose |
|--------|---------|
| `civic_event_id` | Event |
| `user_id` | Attendee |
| `hours_before` | `24` or `1` |

Unique on `(civic_event_id, user_id, hours_before)`.

## Event lifecycle

1. **Create** — host fills event form in citizen or politician portal.
2. **Publish** — event appears on public index and map API.
3. **Manage** — host can edit or view RSVPs; cancel an event.
4. **Cancel** — host marks event `cancelled`; attendees and waitlisted users are emailed.
5. **Complete** — after `starts_at` the public page shows the event has ended.

## RSVP lifecycle

### Without host approval

1. User submits `yes`/`maybe`/`no` + guest count.
2. If capacity is set and `current_attending + guest_count > capacity`, user is placed on the **waitlist**.
3. If capacity is set and a confirmed attendee later changes to `no`, waitlisted users are promoted FIFO and emailed.
4. A `no` RSVP frees capacity but does not trigger notifications.

### With host approval (`rsvp_requires_approval = true`)

1. New RSVPs are created in `pending` status.
2. Host reviews pending RSVPs on the event's RSVP management page.
3. Host approves → `approved`; declines → `declined`.
4. Attendee receives an approval or decline email.

### Waitlist behavior

- Waitlist is FIFO ordered by `created_at`, then `id`.
- Only waitlist entries whose `guest_count` fits the newly available capacity are promoted.
- If multiple spots open, multiple users may be promoted in a single dropout.

## Notifications

All notifications use `EmailTemplate` overrides when available (see keys below). Failures are logged/reported without breaking the request.

| Trigger | Email class | Template key | Recipients |
|---------|-------------|--------------|------------|
| Event is ~24h or ~1h away | `EventReminderMail` | `event_reminder_attendee` | Attending, approved, and waitlisted users who have not already received that reminder |
| User RSVPs or updates to a non-`no` response | `EventHostRsvpMail` | `event_host_rsvp` | Host (`receipt_email` fallback to `user->email`) |
| Confirmed attendee drops out and capacity opens | `EventWaitlistPromotionMail` | `event_waitlist_promotion` | Promoted waitlisted users |
| Host cancels event | `EventCancellationMail` | `event_cancellation_attendee` | All attending, approved, and waitlisted users |
| Host approves pending RSVP | `EventRsvpApprovedMail` | `event_rsvp_approved` | The approved attendee |
| Host declines pending RSVP | `EventRsvpDeclinedMail` | `event_rsvp_declined` | The declined attendee |

### Reminder scheduling

The `events:send-reminders` Artisan command runs hourly. It looks for published events whose `starts_at` falls within ±30 minutes of the 24-hour or 1-hour window from now. For each matching event it sends reminders to users who do not already have an `event_reminders` row for that window.

## Scheduler

`routes/console.php`:

```php
Schedule::command('events:send-reminders')
    ->hourly()
    ->withoutOverlapping();
```

## Admin-editable email templates

Create `email_templates` rows with these keys to override subjects or bodies:

| Key | Default subject |
|-----|-----------------|
| `event_reminder_attendee` | "Reminder: \"{event.title}\" starts in {hours} hour(s)" |
| `event_host_rsvp` | "🎟️ New RSVP for \"{event.title}\"" |
| `event_waitlist_promotion` | "✅ You're off the waitlist: \"{event.title}\"" |
| `event_cancellation_attendee` | "❌ Cancelled: \"{event.title}\"" |
| `event_rsvp_approved` | "✅ You're confirmed for \"{event.title}\"" |
| `event_rsvp_declined` | "Update on your RSVP for \"{event.title}\"" |

`body_override` replaces the entire HTML email body when `is_active` is true and the override is non-empty.

## Routes

### Public

| Route | Controller | Notes |
|-------|------------|-------|
| `GET /events` | `PublicCivicEventController@index` | Public event index |
| `GET /events/{event}` | `PublicCivicEventController@show` | Event detail page |
| `GET /events/{event}/ics` | `PublicCivicEventController@ics` | `.ics` download |
| `POST /events/{event}/rsvp` | `EventRsvpController@store` | Requires auth + verified |

### Host portal (citizen + politician)

| Route | Controller |
|-------|------------|
| `GET /{citizen|politician}/events` | `CivicEventController@index` |
| `GET /{citizen|politician}/events/create` | `CivicEventController@create` |
| `POST /{citizen|politician}/events` | `CivicEventController@store` |
| `GET /{citizen|politician}/events/{event}/edit` | `CivicEventController@edit` |
| `PUT /{citizen|politician}/events/{event}` | `CivicEventController@update` |
| `GET /{citizen|politician}/events/{event}/rsvps` | `CivicEventController@rsvps` |
| `PATCH /{citizen|politician}/events/{event}/rsvps/{rsvp}/approve` | `CivicEventController@approveRsvp` |
| `PATCH /{citizen|politician}/events/{event}/rsvps/{rsvp}/decline` | `CivicEventController@declineRsvp` |
| `PATCH /{citizen|politician}/events/{event}/cancel` | `CivicEventController@cancel` |

Route names follow the portal prefix, e.g. `citizen.events.rsvps`, `politician.events.rsvps.approve`.

## Tests

Coverage lives in `tests/Feature/Blog/CivicEventTest.php`:

- Citizen/politician event CRUD
- Host authorization boundaries
- Public event pages and ICS export
- Map content API
- RSVP yes/waitlist/no flows
- 24h/1h scheduled reminder emails and duplicate suppression
- Host RSVP notification
- Waitlist promotion and promotion email
- Event cancellation attendee email
- Host approve/decline pending RSVP and attendee emails
- Cross-event authorization guard

Latest run: **626 passed, 7 risky**.

## Key files

### Backend

- `app/Models/CivicEvent.php`
- `app/Models/EventRsvp.php`
- `app/Models/EventReminder.php`
- `app/Http/Controllers/Standalone/CivicEventController.php`
- `app/Http/Controllers/Standalone/EventRsvpController.php`
- `app/Http/Controllers/Standalone/PublicCivicEventController.php`
- `app/Console/Commands/SendEventReminders.php`

### Mail

- `app/Mail/EventReminderMail.php`
- `app/Mail/EventHostRsvpMail.php`
- `app/Mail/EventWaitlistPromotionMail.php`
- `app/Mail/EventCancellationMail.php`
- `app/Mail/EventRsvpApprovedMail.php`
- `app/Mail/EventRsvpDeclinedMail.php`

### Views

- `resources/views/standalone/public/events/show.blade.php`
- `resources/views/standalone/public/events/rsvp-card.blade.php`
- `resources/views/standalone/public/events/index.blade.php`
- `resources/views/standalone/events/rsvps.blade.php`
- `resources/views/standalone/{citizen,politician}/events/*.blade.php`
- `resources/views/emails/event-*.blade.php` and `-text.blade.php`

### Routes & scheduler

- `routes/standalone.php` (citizen/politician event groups + public event routes)
- `routes/console.php` (`events:send-reminders` hourly schedule)

### Tests

- `tests/Feature/Blog/CivicEventTest.php`
