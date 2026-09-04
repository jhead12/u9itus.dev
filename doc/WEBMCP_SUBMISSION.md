# WebMCP Challenge — Submission Guide

How to submit **u9itus** to the WebMCP Challenge (<https://webmcp.devpost.com/>).

---

## 0. Status snapshot — 2026-09-03

| Item | State |
|---|---|
| Live demo URL — `https://www.u9itus.com/webmcp` | ✅ deployed, 200 |
| Live API — `https://www.u9itus.com/api/v1/mcp/*` | ✅ serving real data |
| OSS license | ✅ [LICENSE](../LICENSE) (MIT, matches `composer.json`) |
| `robots.txt` agent access | ✅ deployed — `e8acdf90`, see §3.5 |
| **Public repo** | ✅ `github.com/jhead12/u9itus.dev` is public (full production monorepo — see §3) |
| Demo video (< 3 min) | ✅ recorded — paste the URL in §5 / §6 |
| Devpost project entry | ⬜ confirm submitted — see §6 |

**Deadline (per rules): September 3, 2026, 1:00 PM PDT — hard cutoff.** Devpost
locks submissions at the deadline; there is no grace period.

**Repo visibility:** the full `jhead12/u9itus.dev` repo stays **public through
judging**. It will be made private again once judging closes (see §3 / §9).

---

## 1. Required deliverables (from the rules)

- [x] **Public code repository** with an OSS license — `jhead12/u9itus.dev`, MIT.
- [x] **Working, publicly accessible URL** — `https://www.u9itus.com/webmcp`,
      testable in a WebMCP-capable agent (ChatGPT browser / Chrome with WebMCP).
- [x] **Demo video, under 3 minutes**, English, public on YouTube/Vimeo — link in §5.
- [ ] **Text write-up** on the Devpost form (what it does / how it was built /
      how to test) — draft in §6.
- [ ] Entry submitted through the Devpost form before the deadline.

---

## 2. What we're submitting

**u9itus is a first-class WebMCP provider for U.S. civic research.** Instead of an
AI agent scraping the DOM, u9itus registers structured tools the agent calls
directly. **Nine tools** register on `document.modelContext` (with
`navigator.modelContext` / polyfill fallbacks):

| Tool | Purpose |
|---|---|
| `u9itus_current_page` | Describe the u9itus page in view + candidate in focus |
| `u9itus_find_candidates` | Search published candidate / official profiles. Supports `funded_by` — filter to candidates whose FEC/OpenSecrets donor snapshot shows money from a named PAC, committee, advocacy group, industry, or contributor; matched results carry a `funding_match` array citing the specific sources + dollar amounts |
| `u9itus_get_candidate` | Full civic dossier — office, party, transparency IDs, verified news (each with a `source_url`), donor snapshot with raw FEC committee IDs resolved to registry names, upcoming elections |
| `u9itus_compare_candidates` | Side-by-side dossiers for 2–4 candidates |
| `u9itus_list_ballot_measures` | State / county ballot measures with plain-language yes/no meanings; each result carries a `read_more` link ({label, url}) to the full legal text / fiscal analysis |
| `u9itus_watch_ballot_measures` | Register an email to be notified when a state's measures are published (also nudges the on-demand backfill) |
| `u9itus_upcoming_elections` | Election stages and filing deadlines for a state |
| `u9itus_latest_candidate_news` | Most recent verified candidate/official news across every published profile, newest first — one call for "what's the latest" / "who's in the news"; each item links the original story (`source_url`) and its candidate (`profile_url`) |
| `u9itus_submit_candidate_lead` | Queue a spotted candidate for human verification (never publishes) |

Source of truth:
- Tool registration — [resources/js/webmcp/index.js](../resources/js/webmcp/index.js) (~400 lines)
- Backend — [app/Http/Controllers/Api/WebMcpController.php](../app/Http/Controllers/Api/WebMcpController.php)
- Routes — [routes/api.php](../routes/api.php) (`/api/v1/mcp/*`, 8 endpoints)
- Demo console — [resources/views/webmcp.blade.php](../resources/views/webmcp.blade.php) → `/webmcp`
- Architecture doc — [doc/WEBMCP.md](WEBMCP.md)

---

## 3. Repo — public production monorepo (decided)

We went with a single public repo (`jhead12/u9itus.dev`, MIT) rather than
extracting a separate `u9itus-webmcp`. The relevant integration is small and
easy to point at; the surrounding platform is context that strengthens the
"live on a real production site" argument, not noise to hide.

**Pointer for judges** — put these paths in the Devpost write-up (§6):
- `resources/js/webmcp/index.js` — the whole tool catalogue
- `app/Http/Controllers/Api/WebMcpController.php` — the backend
- `routes/api.php` (`/api/v1/mcp` group) — the endpoints
- `doc/WEBMCP.md` — architecture
- `/webmcp` on the live site — the console + connection badge

The [README.md](../README.md) has a top "WebMCP Civic Agent" section pointing at
the same.

**Pre-public secret pass — done.** `.env*` is not tracked (`.env.example` /
`.env.testing` only). History grep for `sk_live` / AWS keys / private-key blocks:
every `sk_live` hit is a prefix check (`str_starts_with($key, 'sk_live_')`) or a
`sk_live_fake_…` test fixture.

**Cleanup TODO (low priority):** `legacy/.vagrant/machines/Vaprobash/virtualbox/private_key`
is the well-known throwaway Vagrant "insecure" key (grants nothing) but trips
secret scanners — `git rm -r legacy/.vagrant/` when convenient.

**After judging:** `gh repo edit jhead12/u9itus.dev --visibility private
--accept-visibility-change-consequences`. Do **not** private it while a live
Devpost entry links it — judges would hit a 404.

---

## 3.5. robots.txt — agent access (deployed)

`public/robots.txt` previously sent `User-agent: ChatGPT-User` → `Disallow: /`
plus `Disallow: /api` for all bots, so ChatGPT (and any robots-respecting agent)
refused to fetch `/webmcp` — "Failed to fetch restricted URL".

Fixed and deployed in `e8acdf90` — interactive agents (`ChatGPT-User`,
`OAI-SearchBot`, `Claude-Web`, `anthropic-ai`, `PerplexityBot`) now get
`Allow: /webmcp` + `Allow: /api/v1/mcp/` before their `Disallow: /`; training
crawlers (`GPTBot`, `CCBot`, `Bytespider`, …) stay fully blocked.

Verify on prod:

```bash
curl -s https://www.u9itus.com/robots.txt | grep -A2 "ChatGPT-User"
```

Note: [routes/standalone.php](../routes/standalone.php) also defines a
`/robots.txt` route, but the static `public/robots.txt` is served first and wins.

---

## 4. License — done

[LICENSE](../LICENSE) (MIT) is at the repo root and matches the
`"license": "MIT"` in `composer.json`.

---

## 5. Demo video — recorded

**URL:** _paste the YouTube/Vimeo link here — must be Public, English, under 3 min._

Reference shot list (what it should cover):

| Time | Shot | Point |
|---|---|---|
| 0:00–0:20 | `/webmcp` loading; badge flips to **connected** | "u9itus is a civic-transparency platform, and now a WebMCP provider — any AI agent calls its research tools directly, no scraping." |
| 0:20–0:35 | Scroll the tool catalogue | "Nine tools: search candidates (incl. a donor/PAC funding filter), full dossier, compare, ballot measures, elections, latest candidate news, and a human-in-the-loop lead submission." |
| 0:35–1:30 | In a WebMCP agent on `/webmcp`: *"Use u9itus to find who represents Ohio's 8th district, then pull their full dossier."* Show `u9itus_find_candidates` → `u9itus_get_candidate`. | "Right tool, uuid passed through, structured data back — transparency IDs, verified news, donor snapshot with committee names resolved." |
| 1:30–2:05 | *"Which Texas candidates are funded by AIPAC?"* Show `u9itus_find_candidates` with `funded_by`, and the `funding_match` citations. | "One query. The answer cites the specific PACs and dollar amounts." |
| 2:05–2:30 | Live console on `/webmcp` running the same endpoint; then the `doc/WEBMCP.md` diagram. | "Same JSON API behind the tools and the site. Small, MIT-licensed, and a template any civic-data site can copy." |

---

## 6. Devpost form — field by field

Go to <https://webmcp.devpost.com/> → **Submit a project**.

- **Project name:** `u9itus — WebMCP civic-research agent`
- **Tagline:** *Any AI agent can research U.S. candidates, elections, ballot measures, and campaign finance directly against verified civic data — via WebMCP, no scraping.*
- **"What it does":** paste §2's table + this paragraph:
  > u9itus registers nine civic-research tools on the browser's WebMCP surface.
  > An agent can search 789 published candidate/official profiles, pull a full
  > verified dossier, compare candidates, filter candidates by who funds them
  > (PAC / advocacy group / industry, with the matching contributions cited),
  > browse state ballot measures with links to the full text, get the latest
  > verified candidate news, and submit a spotted candidate into a
  > human-reviewed pipeline. Same JSON API powers the tools and the website.
- **"How I built it":** Laravel 12 API (`/api/v1/mcp/*`, 8 endpoints) over an
  existing 789-profile civic dataset; a ~400-line browser module registers the
  tools via `document.modelContext` (with `navigator.modelContext` / polyfill
  fallbacks, handling every known registration shape) and lazy-loads so pages
  with no agent pay nothing; per-page context injected via
  `window.__U9ITUS_MCP__`. Finance filter runs cross-driver `LOWER(json) LIKE`
  over nightly FEC/OpenSecrets donor snapshots; committee IDs resolve against a
  registry table seeded by the same enrichment.
- **"Challenges / what's next":** Chrome MV3 extension using the built-in Prompt
  API for a browse-anywhere civic assistant; write tools gated behind voter
  auth; deeper FEC / Google Civic / Congress.gov integration; committee → org
  curation so every spender resolves to a recognizable name.
- **"Built with" tags:** `webmcp`, `laravel`, `php`, `javascript`, `model-context-protocol`, `civic-tech`
- **Testing instructions for judges** (put in the description — critical):

  > **Live:** <https://www.u9itus.com/webmcp>
  > 1. Open in ChatGPT's browser, or Chrome with WebMCP enabled. The badge flips
  >    to "connected" when the **9 tools** register.
  > 2. Ask: *"Use u9itus to find who's running for US Senate in Ohio,"* then
  >    *"pull the u9itus dossier for that candidate and compare with another."*
  > 3. Try: *"Which Texas candidates are funded by AIPAC?"* — one call, with the
  >    contributions cited.
  > 4. No agent handy? The **Live console** on the same page calls the identical
  >    JSON endpoints, e.g. `GET /api/v1/mcp/candidates?q=warren&limit=3`.
  > **Code:** `github.com/jhead12/u9itus.dev` — start at
  > `resources/js/webmcp/index.js` and `app/Http/Controllers/Api/WebMcpController.php`.

- **Links:** live URL, `github.com/jhead12/u9itus.dev`, the video from §5.
- **Image / thumbnail:** screenshot of `/webmcp` with the "connected" badge.

---

## 7. Judging criteria — what to emphasize

| Criterion | Our argument |
|---|---|
| **WebMCP Leverage** | Tools are the whole interface — the agent never touches the DOM. Registration handles every `modelContext` shape; page context is fed structurally. Nine tools: read, a campaign-finance filter that returns cited evidence, a cross-candidate news feed, and a safe human-in-the-loop write. |
| **Execution** | Live on a real production site with a real 789-profile dataset, not a toy. Rate-limited, validated, 404-guarded, cross-driver. Lazy-loaded — zero cost when no agent is present. Fallback console proves the endpoints. |
| **Potential Impact** | Civic information is high-stakes and hard to search well. Making it agent-native — verified, sourced, comparable, with money-trail and news built in — is a template any government-data or transparency site can copy. |

---

## 8. Pre-submit smoke test

```bash
curl -s -o /dev/null -w "webmcp page: %{http_code}\n" https://www.u9itus.com/webmcp
curl -s "https://www.u9itus.com/api/v1/mcp/candidates?q=warren&limit=3" | jq '.count'
curl -s "https://www.u9itus.com/api/v1/mcp/candidates?state=TX&funded_by=AIPAC" | jq '.total'
curl -s "https://www.u9itus.com/api/v1/mcp/candidate-news?limit=3" | jq '.count'
curl -s "https://www.u9itus.com/api/v1/mcp/ballot-measures?state=CA&limit=1" | jq '.results[0].read_more'
# public repo reachable while logged out:
curl -s -o /dev/null -w "repo: %{http_code}\n" https://github.com/jhead12/u9itus.dev
curl -s https://www.u9itus.com/robots.txt | grep -A2 "ChatGPT-User"
```

All should succeed. Then do one real agent run end to end.

---

## 9. After you submit

- **Freeze the live URL and the public repo** until judging closes — don't push
  breaking changes to `master` that would take `/webmcp` or the API down.
- Keep the Railway `web` service running (don't scale to zero).
- You can keep editing the Devpost entry up to the deadline, not after.
- **Once judging closes:** make `jhead12/u9itus.dev` private again, and
  `git rm -r legacy/.vagrant/`.

---

## 10. Remaining steps

1. **§8** — run the smoke test against prod. *(~5 min)*
2. **§5 / §6** — paste the video URL into the doc and the Devpost form.
3. **§6** — fill in the Devpost form, paste links, submit.
4. Confirm the confirmation email.
