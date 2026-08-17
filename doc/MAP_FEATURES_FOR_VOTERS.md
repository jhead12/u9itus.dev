# Interactive Map — Feature List & Voter Value

A full inventory of the 3D U.S. map's features (`resources/js/map/`), with a short note on why each one matters to a voter using the site. Grouped to mirror how a voter actually moves through the map: find yourself → see who represents you → dig into a candidate → save/follow what you care about.

## Finding Your Place

- **Search palette** (name/state/district/business search, `/` to open) — type a state, a district ("CA-12"), a candidate's name, or a local business, and jump straight there. Saves a voter from hunting through menus when they already know who or where they're looking for.
- **"Find My District" geolocation** (`L` key) — one click uses the browser's location to fly straight to the voter's own district and open its panel. The fastest path from "I don't know my district" to "here's who represents me."
- **Deep-linking / shareable URLs** — every district and candidate view has a real URL (`?state=&district=&slug=`) that can be pasted, texted, or shared. Lets a voter send a friend or family member directly to "your candidates" instead of "go search for yourself."
- **Region → State → District drill-down + breadcrumb trail** — zooming follows a natural national → state → district path, with a clickable trail back up. Keeps a voter oriented instead of getting lost after a few clicks.

## Seeing Who Represents You

- **District panel** — current U.S. Representative, any primary/general challengers depending on where the election cycle stands, district population, ballot measures with plain-language "what a Yes/No vote means," a link to find your polling place, and local civic news (redistricting, polling-place changes) scoped to your district. This is the core "am I informed about my own race" view.
- **State panel** — statewide offices (Governor, AG, etc.) with current officeholders and 2026 candidates, election/primary/filing dates, and ballot measures for the whole state. Answers "what's on my ballot beyond my district."
- **Candidate/officeholder markers** (capitol stars, statewide candidate pins, city markers) — every major race and officeholder is a clickable pin on the map itself, not buried in a list. Turns "who's running" into something a voter can literally see on the map of where they live.
- **Ballot measures with explanations** — expandable cards explain what a Yes/No vote actually does, not just the measure's title. Directly reduces the most common source of ballot confusion — under-informed votes on measures/props.

## Understanding a Candidate

- **Candidate profile drawer — Overview tab** — bio, recent votes (Vote Smart), campaign videos, recent news, press releases, and upcoming events for that candidate. Gives a voter a fast, one-stop "who is this person" without leaving the map.
- **Candidate profile drawer — Economy tab** — top contributors, industry support breakdown, FEC financial summary (raised/spent/cash on hand/debt), endorsements, and PAC-affiliation badges (explicitly labeled as inferred, not confirmed). Lets a voter follow the money behind a candidate — who's actually funding them — in plain terms instead of digging through FEC.gov themselves.
- **"Moments" tab (viral clips)** — short video clips of the candidate with view counts. Gives a faster, more human read on a candidate than a text bio alone.
- **Contact tab** — links to the candidate's official site, Ballotpedia page, and all other candidates running in the same race. One place to go deeper or compare options, rather than a voter having to separately Google each candidate.
- **City view (clicking a city instead of a person)** — shows a city's population, political leaning, and its congressional district/representative, plus Census economic stats (poverty rate, median income, education). Useful for a voter checking on a place they're moving to, or comparing their city to a neighboring one.

## Personalizing the Map

- **Save/favorite a district or city** (star button) — bookmark the districts/cities a voter actually cares about (home, family, a place they're moving to), with guest support (no account needed, cookie-backed) that carries over automatically once they do sign up.
- **Saved Boundaries panel** — one place to see and jump back to everything a voter has saved, instead of re-searching each time.
- **Weekly saved-places email digest** — opt in once and get a weekly email of what's new (news, endorsements) for the districts/cities saved — keeps a voter informed between visits without them having to remember to check back.
- **Layers panel with persistent preferences** — toggle on only what's relevant (party colors, population density, businesses, content, candidates) and it's remembered next visit. Lets a voter tailor the map to what they actually want to see instead of a fixed, cluttered default view.

## Extra Context Layers

- **Population density & party-color modes** — shade the whole map by population or by which party holds the governorship, at a glance. Useful for a voter who wants the "big picture" before drilling into their own race.
- **Local business & civic content pins** — opt-in local businesses and geo-tagged blog posts/events show up as pins on the map. Connects the civic side of the site to the local community a voter actually lives in.
- **Legend (regions / party control)** — a live key for whatever coloring mode is active, so the map's colors are never a mystery.

## Getting Started

- **Guided 8-step tour** (auto-launched once, replayable anytime) — walks a first-time voter through search, zoom, tilt, and the key toggles in under a minute, so the 3D map doesn't feel intimidating on first visit.
- **Keyboard shortcuts + help dialog** (`/`, `S`, `R`, `O`, `L`, zoom/tilt keys) — power-user shortcuts for voters who come back regularly and want to move faster than mouse-only navigation.
- **Mobile bottom-sheet menu** — the same layers/controls collapse into a thumb-friendly sheet on phones, so the experience isn't compromised for voters who aren't on a desktop.

## Where the Data Comes From
Most of what shows up here isn't hand-entered — it's synced or fetched live: congress-legislators + Ballotpedia for candidates/officeholders, Vote Smart for bios/votes/election dates, FEC/OpenSecrets for campaign finance, U.S. Census for population and city economics, and the site's own news pipeline for local civic coverage. Worth knowing so a voter (or anyone reviewing this list) understands the map is reflecting outside public-record data, not editorial claims made by the site itself.
