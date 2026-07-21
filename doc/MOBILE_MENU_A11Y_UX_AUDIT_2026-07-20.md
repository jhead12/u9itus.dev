# Mobile Menu UX & Accessibility Audit — Politician & Voter

**Date:** 2026-07-20
**Scope:** Mobile navigation menus for politician and voter roles across the dashboard layouts and the in‑map experience.
**Audited by:** Claude (code review agent)
**Status:** Findings recorded; no fixes applied yet.

---

## Surfaces in scope

Three distinct mobile menu surfaces are in play, each built differently:

| Surface | Files | Pattern |
|---|---|---|
| **A. Politician dashboard** sidebar | `resources/views/standalone/layouts/dashboard.blade.php` | Tailwind slide‑in `<aside>` + vanilla `toggleSidebar()` |
| **B. Voter** sidebar + favorites drawer | `resources/views/layouts/voter.blade.php` | Tailwind slide‑in `<aside>` + favorites panel |
| **C. In‑map** mobile menu + politician drawer | `resources/views/standalone/public/us-map.blade.php`, `resources/js/map/ui/mobile-menu.js`, `resources/js/map/ui/politician-drawer.js`, `resources/css/map.css` | Plain JS + CSS transforms |

The map surface (C) is meaningfully more accessible than the two dashboard surfaces (A, B). Most issues cluster in A and B.

### Severity legend
- 🔴 **Critical** — breaks keyboard/SR access or shows wrong state.
- 🟠 **Serious** — partial a11y failure or real mobile UX friction.
- 🟡 **Minor** — polish/consistency.

---

## A. Politician dashboard sidebar — `standalone/layouts/dashboard.blade.php`

### Accessibility

- 🔴 **A1. Hamburger button has no `aria-label`, `aria-expanded`, or `aria-controls`** — `dashboard.blade.php:373`
  ```html
  <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white">
  ```
  Screen readers announce only "button". Compare to the map hamburger (`us-map.blade.php:288`), which does this correctly. State is never communicated, so a SR user can't tell if the menu is open.

- 🔴 **A2. Closed sidebar links stay in the tab order** — `dashboard.blade.php:106`, `607-612`
  The sidebar is hidden only via `transform -translate-x-full`. `toggleSidebar()` flips two classes and nothing else — no `aria-hidden`, no `inert`, no `hidden`. On mobile, a keyboard user can Tab past the hamburger straight into invisible, off‑screen nav links. Most impactful a11y bug in this surface.

- 🟠 **A3. No focus management on open/close** — `dashboard.blade.php:607`
  Opening the hamburger doesn't move focus into the sidebar; closing doesn't return focus to the hamburger. No Escape‑to‑close on the sidebar itself (only the overlay button has a keydown handler, `:364`).

- 🟠 **A4. Active nav item not programmatically current** — `dashboard.blade.php:124` et al.
  Active state is conveyed only by `.sidebar-link.active` styling (color). No `aria-current="page"` on the matching link — SR users get no "current page" signal.

- 🟠 **A5. `<nav>` landmark is unlabeled** — `dashboard.blade.php:119`
  With multiple potential nav regions, this `<nav>` has no `aria-label` ("Politician navigation", etc.).

- 🟡 **A6. Decorative SVGs not hidden from SR** — every nav link, e.g. `:125`
  `<svg class="w-4 h-4" ...>` precedes each text label but lacks `aria-hidden="true"`. Icons are decorative because text follows.

- 🟡 **A7. Notification bell missing `aria-expanded`/`aria-controls`** — `:385-397`
  `aria-label="Notifications"` is present, but the bell toggles a panel (`x-show="open"`) without exposing expanded state or what it controls. Dropdown panel also has no `role`/region label.

- 🟡 **A8. "Start Here" toggle button state not exposed** — `:483`
  Toggles a `hidden` panel (good — `hidden` removes from a11y tree) but the toggle button has no `aria-expanded`/`aria-controls`.

### UX

- 🟠 **A9. Background page scroll isn't locked when the sidebar is open** — body remains scrollable behind the drawer. Common cause of "lost place" on mobile.

- 🟡 **A10. No swipe gesture to close** the sidebar; overlay‑tap only. Acceptable, but mobile users expect edge‑swipe.

- 🟡 **A11. Full‑screen overlay is a `<button>`** (`:360`). Semantically odd (a button with no content). Works, but a `div` with `role` would be cleaner.

---

## B. Voter sidebar + favorites drawer — `layouts/voter.blade.php`

### Accessibility

- 🟠 **B1. Hamburger has `aria-label` but no `aria-expanded`/`aria-controls`** — `:88-94`
  Better than the politician hamburger (it has a label), but still no expanded state.

- 🔴 **B2. Closed sidebar links stay focusable** — `:246`, `659-666`
  Same root cause as A2. `toggleSidebar()` only toggles `-translate-x-full`/`hidden` on the overlay. The aside has `aria-hidden` nowhere. Off‑screen nav links are keyboard‑reachable on mobile.

- 🟠 **B3. No focus management / Escape‑to‑close on the sidebar** — `:659`
  The favorites drawer *does* handle Escape (`:512-516`) — the main sidebar does not. Inconsistent within the same layout.

- 🟠 **B4. Active nav item has no `aria-current`** — `:316-321`
  Same as A4; active state is styling only.

- 🟠 **B5. `<nav>` unlabeled** — `:290`.

- 🟡 **B6. Decorative SVGs not `aria-hidden`** — `:322-326`.

- 🟡 **B7. Favorites toggle missing `aria-expanded`** — `:107-115`
  This drawer is the best‑implemented of the three (it has `aria-controls`, and `aria-hidden` on the panel *is* toggled correctly at `:470`). But the trigger button's expanded state still isn't exposed, and there's no focus trap or focus move into the panel.

- 🟡 **B8. User dropdown has no a11y wiring** — `:190-238`
  Trigger button has no `aria-label`/`aria-expanded`/`aria-controls`; dropdown relies on `@click.outside` (useless for keyboard users), no arrow‑key nav, no Escape. This is where Sign Out lives on mobile, so it's load‑bearing.

- 🟡 **B9. Trust‑score bar isn't a `progressbar`** — `:366-379`
  The numeric "80/100" is announced (good), but the visual `<div>` bar has no `role="progressbar"`/`aria-valuenow` and isn't marked `aria-hidden`, so it's ambiguous to SRs.

- 🟡 **B10. Notification bell missing `aria-expanded`/`aria-controls`** — `:120` (same gap as A7).

### UX

- 🟡 **B11. Voter nav is a flat 9‑item list with no section headers**, while the politician sidebar groups with "Overview / Campaigns / Insights / Account" headers (`dashboard.blade.php:121,129,143,157`). Scannability and IA consistency both suffer.

- 🟡 **B12. Two left/right drawers can stack** — voter sidebar (left) and favorites panel (right) can both be open on mobile, each with its own overlay (`z-20` vs `z-40`/`z-50`). Edge case, but the overlays fight each other.

- 🟡 **B13. Background scroll not locked** for either the sidebar or favorites drawer.

---

## C. In‑map mobile menu + politician drawer — `us-map.blade.php`, `mobile-menu.js`, `politician-drawer.js`, `map.css`

### Accessibility

✅ **Good baseline.** The map hamburger (`us-map.blade.php:288`) is the model the dashboards should copy: `aria-label`, `aria-expanded`, `aria-controls`, and `mobile-menu.js:12,18` keeps `aria-expanded` in sync. The pol drawer opens with `role="dialog" aria-modal="true" aria-labelledby="pol-drawer-name"`, moves focus to the close button (`politician-drawer.js:302`), and closes on Escape (`:564`). Tabs use `role="tablist"/"tab"/"tabpanel"` with `aria-selected` synced (`:572`).

- 🔴 **C1. The pol drawer's `hidden` attribute is overridden by CSS, leaving closed‑drawer contents focusable** — `map.css:1062`
  ```css
  #pol-drawer[hidden] { display: flex; } /* keep flex layout; hidden handled by transform */
  ```
  Because the drawer is hidden by transform (off‑screen), not `display:none`, its close button, tabs, and links remain in the tab order when "closed." The `hidden` attr set on close (`politician-drawer.js:318`) is dead — CSS overrides it. Same class of bug as A2/B2. Fix: add `inert` (or `visibility:hidden`) when closed, or toggle `display:none` after the transition.

- 🔴 **C2. Mobile "Districts" button shows stale ON/OFF state** — `us-map.blade.php:301-303`, `mobile-menu.js:27-30`
  The button's label is hard‑coded `OFF`. `mobBtnDistricts` clicks the hidden `#btn-districts` and closes the menu, but never updates the mobile button's own label or `aria-pressed`. Opening the menu later always says "OFF" regardless of the real toggle state. Misleading state feedback. Add `aria-pressed` and update the text.

- 🟠 **C3. Lazy video placeholder isn't keyboard‑activatable** — `us-map.blade.php:395-396`, `politician-drawer.js:552-561`
  The placeholder is `role="button" tabindex="0"` with `onclick="window.__loadPolVideo(this)"` but **no keydown handler**. Enter/Space does nothing. The rest of the map consistently pairs `onclick` with `onkeydown` for `role=button` divs (e.g. legend `:402`, drag handle `:413`); this one was missed.

- 🟠 **C4. No focus trap in any map dialog** — pol drawer, candidate popup, search overlay, keyboard help, tutorial. `aria-modal="true"` is set but Tab can escape to the map behind. Focus is moved *into* the pol drawer (good) but not contained.

- 🟠 **C5. Focus not returned to trigger when the pol drawer closes** — `politician-drawer.js:314-320`
  `closePolDrawer()` doesn't restore focus to the marker/list item that opened it; focus falls to `<body>`. Disorienting for keyboard users after closing.

- 🟡 **C6. Tabs aren't full ARIA tabs pattern** — `us-map.blade.php:374-379`, `politician-drawer.js:567-584`
  Tabs lack `aria-controls="pol-body"`, and arrow‑key navigation between tabs isn't implemented (only click). Single‑tabpanel‑swap is acceptable, but `aria-controls` is expected.

- 🟡 **C7. Mobile menu items lack `role="menuitem"`** — `us-map.blade.php:301,304`
  The container is `role="menu"` but the buttons inside have no `role="menuitem"`, unlike the Controls menu which is consistent (`:214`+).

- 🟡 **C8. Vague close labels** — `#cand-popup-close` is `aria-label="Close"` (`:333`); the pol drawer's is "Close politician profile" (good). Make the popup's "Close candidate popup".

- 🟡 **C9. `pol-name` truncates without `title`** — `map.css:1092-1096` (`white-space:nowrap; text-overflow:ellipsis`). Long politician names clip with no accessible full‑name fallback.

### UX

- 🟠 **C10. Heavy top‑bar chrome on mobile.** On a phone the voter/politician map shows: 64px app header (from the dashboard/voter layout) + 48px in‑map top bar + 28px breadcrumb ≈ 140px of stacked chrome before the map. Six buttons cram into the 48px in‑map bar (Back, Find‑District, Search, Layers, Controls, hamburger). Tap targets are correctly enlarged to 44px (`map.css:851`), so they survive, but it's dense.

- 🟠 **C11. The mobile hamburger menu is nearly redundant with the Controls dropdown.** The mobile drawer exposes only 2 actions (Districts, Reset) — both also live in the Controls dropdown, which is *also* visible on mobile. The hamburger adds little. Consolidate (e.g., let the hamburger open the full Controls menu on mobile) and drop the separate 2‑item drawer.

- 🟡 **C12. Pol‑drawer bottom sheet has no drag handle** — `map.css:1193-1212`. The `#info-panel` bottom sheet gets a `.panel-drag-handle` (`:880`); the pol‑drawer bottom sheet doesn't. Inconsistent mobile affordance for the same bottom‑sheet pattern.

- 🟡 **C13. No Escape‑to‑close on the mobile menu** — `mobile-menu.js` handles outside‑click but not Escape.

---

## Cross‑cutting themes

1. **The "off‑screen but still focusable" bug appears in all three surfaces** (A2, B2, C1). It's the single highest‑value fix: when a drawer closes, its focusable descendants must leave the tab order — via `inert`, `hidden` (real `display:none`), or `aria-hidden` + `tabindex="-1"`. The pol drawer's CSS `#pol-drawer[hidden]{display:flex}` actively defeats the `hidden` attribute.

2. **`aria-current="page"` is missing everywhere** (A4, B4). Every active sidebar link is styled‑only. One‑line attribute add per link.

3. **Drawer open/close is purely visual** across A and B — no `aria-expanded` on triggers, no focus move, no Escape, no scroll lock. The map surface (C) shows the target pattern; lift it into the dashboards.

4. **Two implementations of the same dashboard pattern** (`toggleSidebar()` defined separately in `dashboard.blade.php:607` and `voter.blade.php:659`, each referencing a different sidebar ID). Consolidating into one accessible helper (with `aria-expanded`, `aria-hidden`/`inert`, focus move, Escape, scroll lock) would fix A1–A3, B1–B3, B13 in one stroke.

5. **Inconsistent IA**: politician sidebar uses section headers; voter sidebar doesn't. Map's two bottom sheets inconsistently show a drag handle. Pick one pattern per surface class.

---

## Recommended fix order (impact ÷ effort)

1. **C1** — stop overriding `[hidden]` on `#pol-drawer`; use `inert`/`visibility` so the closed drawer isn't focusable. *(Critical, ~10 lines.)*
2. **A2 / B2** — make `toggleSidebar()` set `aria-hidden` + `inert` on the aside when closed (or move it to `display:none` post‑transition). *(Critical, ~5 lines each.)*
3. **A1 / B1 / B8** — add `aria-label`/`aria-expanded`/`aria-controls` to the hamburger(s) and user dropdown; sync in `toggleSidebar()`. *(Serious, small.)*
4. **C2** — update the mobile Districts button label + `aria-pressed` from real state. *(Serious, ~5 lines.)*
5. **C3** — add Enter/Space handler to the lazy‑video placeholder. *(Serious, 3 lines.)*
6. **A4 / B4** — add `aria-current="page"` to active links. *(Minor but trivial and high‑value for SR users.)*
7. **C4 / C5** — focus trap + focus‑return for the pol drawer (and reuse for other map dialogs). *(Serious, moderate.)*
8. **A3 / B3 / C13** — Escape‑to‑close on sidebars and the mobile menu; focus return. *(Serious, small.)*
9. **B11** — add section headers to the voter nav to match the politician sidebar. *(UX polish.)*
10. **C11** — consolidate the 2‑item mobile menu into the Controls dropdown. *(UX cleanup.)*

Items 1–5 are the ones to treat as blocking for an accessibility pass; 6–10 are quick wins / polish.

---

## Suggested first PR

A good first PR with minimal blast radius covers the four critical/serious findings:

- Consolidated `toggleSidebar()` helper (A1, A2, A3, B1, B2, B3, B13) shared by both dashboard layouts.
- Map `#pol-drawer` `[hidden]` / `inert` fix (C1).
- Mobile Districts button state + `aria-pressed` (C2).
- Lazy‑video placeholder keydown handler (C3).

Remaining findings can follow as a second pass.