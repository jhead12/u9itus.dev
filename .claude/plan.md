# Citizen Dashboard Layout Alignment Plan

## Problem
The citizen dashboard (`resources/views/standalone/citizen/dashboard.blade.php`) currently stacks several full-width cards with inconsistent styling:

- Welcome banner: gradient + flex layout
- Campaigns CTA: `bg-slate-800/60`, flex row
- Blog Posts CTA: `bg-slate-800/60`, flex row
- 2FA row: `rounded-2xl`, `border-slate-700/60`

These cards are all the same width but use different background opacities, border colors, border radii, and content patterns, which makes the page feel uneven. The admin dashboard (`resources/views/standalone/admin/dashboard.blade.php`) was recently tightened up and now uses a consistent grid-based layout:

- A simple gradient welcome banner
- A `stat-card` grid with consistent cards
- A two-column recent-activity grid
- A quick-actions grid

The shared layout already defines `.stat-card` in its embedded styles, so we should use that same visual language on the citizen dashboard.

## Files to Change

1. `resources/views/standalone/citizen/dashboard.blade.php`
2. `app/Http/Controllers/Standalone/CitizenController.php` (minor: pass a few extra derived stats so the new stat grid has real numbers)

## Proposed Layout Changes

### 1. Welcome banner
Keep the citizen-specific welcome banner but simplify the markup and styling to match the admin dashboard's banner pattern:

- `bg-gradient-to-r from-amber-500/10 to-slate-800/50 border border-amber-500/20 rounded-xl p-6`
- Remain a flex row on larger screens so the "Switch to Voter Portal" button can stay in the header if the user has the voter role.

### 2. Stats grid
Add a `grid grid-cols-2 lg:grid-cols-4 gap-4` section using the shared `.stat-card` class, populated with:

- **Campaigns** – total campaign count (existing `$campaignCount`)
- **Blog Posts** – `$citizen->posts()->count()`
- **Civic Events** – `$citizen->events()->count()` (already a relationship; quick-count)
- **Credit Balance** – `$citizen->credit_balance` formatted as currency

This matches the admin dashboard's first stats grid and gives the citizen page the same rhythmic card structure.

### 3. Quick actions grid
Replace the individual CTA cards with a `grid grid-cols-2 sm:grid-cols-4 gap-3` quick-actions section matching the admin page:

- New Campaign (amber primary action)
- View Campaigns
- New Post
- View Posts
- Billing & Credits
- New Event
- View Events
- Two-Factor Authentication (moved here from its own standalone row)

Each action uses the same card style as admin quick actions: `bg-slate-800/50 border border-slate-700/50 hover:border-{accent}-500/40 rounded-xl p-4 text-center transition group` with a small icon and label.

### 4. Two-column activity section (optional, if useful)
If the citizen has recent campaigns or posts, show them in a two-column grid of list cards identical to the admin "Recent Registrations" / "Recent Campaigns" section. If not, this section can be omitted to keep the page light.

## Controller Changes (minimal)

In `CitizenController::dashboard()`:

- Keep existing `$user`, `$citizen`, `$campaignCount`.
- Add `$postCount = $citizen?->posts()->count() ?? 0;`
- Add `$eventCount = $citizen?->events()->count() ?? 0;`
- Add `$creditBalance = $citizen?->credit_balance ?? 0;`
- Optionally pass `$recentCampaigns` and `$recentPosts` if we implement the two-column activity section.

These are all derived from existing relationships and do not introduce new database tables or heavy queries.

## Visual Consistency Rules

- All dashboard cards should use `rounded-xl` (not `rounded-2xl`) and `bg-slate-800/50 border border-slate-700/50`.
- Use the existing `.stat-card` class where appropriate.
- Keep spacing consistent with `space-y-6` between major sections and `gap-4` / `gap-3` inside grids.
- Preserve all existing conditional behavior: voter portal switch, billing link with current balance, "View All" links only when content exists, etc.

## Verification

- Run existing citizen feature tests to make sure no behavior is broken.
- Render the citizen dashboard locally (or via browser screenshot) to confirm the layout now mirrors the admin page's grid rhythm.
- Confirm all existing route links still resolve after the view refactor.
