# WebMCP Challenge — Submission Guide

How to submit **u9itus** to the WebMCP Challenge (<https://webmcp.devpost.com/>).

---

## 0. Status snapshot — 2026-09-03

| Item | State |
|---|---|
| Live demo URL — `https://www.u9itus.com/webmcp` | ✅ deployed, 200 |
| Live API — `https://www.u9itus.com/api/v1/mcp/*` | ✅ serving real data |
| OSS license | ✅ added — [LICENSE](../LICENSE) (MIT, matches `composer.json`) |
| `robots.txt` agent access | ✅ fixed locally — see §3.5 (needs deploy) |
| **Public repo** | ❌ **blocker** — `github.com/jhead12/u9itus.dev` is private. See §3. |
| Demo video (< 3 min) | ⬜ not recorded — see §5 |
| Devpost project entry | ⬜ not started — see §6 |

**Deadline: today, September 3, 2026, 1:00 PM PDT — hard cutoff.** Devpost locks
submissions at the deadline; there is no grace period. Submit at least 30 min early.

---

## 1. Required deliverables (from the rules)

- [ ] **Public code repository** with an OSS license (MIT is fine).
- [ ] **Working, publicly accessible URL** a judge can load and test in a
      WebMCP-capable agent (ChatGPT browser / Chrome with WebMCP).
- [ ] **Demo video, under 3 minutes**, in English, uploaded public on
      YouTube or Vimeo, link in the submission.
- [ ] **Text write-up** on the Devpost form (what it does / how it was built /
      how to test).
- [ ] Entry submitted through the Devpost form before the deadline.

---

## 2. What we're submitting

**u9itus is a first-class WebMCP provider for U.S. civic research.** Instead of an
AI agent scraping the DOM, u9itus registers structured tools the agent calls
directly:

| Tool | Purpose |
|---|---|
| `u9itus_current_page` | Describe the u9itus page in view + candidate in focus |
| `u9itus_find_candidates` | Search published candidate / official profiles |
| `u9itus_get_candidate` | Full civic dossier — office, party, transparency IDs, verified news, donors, elections |
| `u9itus_compare_candidates` | Side-by-side dossiers for 2–4 candidates |
| `u9itus_list_ballot_measures` | State / county ballot measures with plain-language yes/no meanings |
| `u9itus_upcoming_elections` | Election stages and filing deadlines for a state |
| `u9itus_submit_candidate_lead` | Queue a spotted candidate for human verification (never publishes) |

Source of truth:
- Tool registration — [resources/js/webmcp/index.js](../resources/js/webmcp/index.js)
- Backend — [app/Http/Controllers/Api/WebMcpController.php](../app/Http/Controllers/Api/WebMcpController.php)
- Routes — [routes/api.php](../routes/api.php) (`/api/v1/mcp/*`)
- Demo console — [resources/views/webmcp.blade.php](../resources/views/webmcp.blade.php) → `/webmcp`
- Architecture doc — [doc/WEBMCP.md](WEBMCP.md)

---

## 3. Fix the repo (do this FIRST — it's the only blocker)

The judges need to read the code. The main app repo is private and holds the
whole business (env references, legacy code, unrelated features). **Do not flip
the entire repo public under deadline pressure.** Pick one:

### Option A — separate public repo `u9itus-webmcp` (recommended)

A small, clean repo with just the WebMCP integration + a README that links to the
live site. ~20 minutes.

```bash
mkdir ~/u9itus-webmcp && cd ~/u9itus-webmcp
git init

# copy the integration files, preserving paths
mkdir -p app/Http/Controllers/Api resources/js/webmcp resources/views doc
cp /Volumes/PRO-BLADE/Github/u9itus.dev/app/Http/Controllers/Api/WebMcpController.php app/Http/Controllers/Api/
cp /Volumes/PRO-BLADE/Github/u9itus.dev/resources/js/webmcp/index.js               resources/js/webmcp/
cp /Volumes/PRO-BLADE/Github/u9itus.dev/resources/views/webmcp.blade.php           resources/views/
cp /Volumes/PRO-BLADE/Github/u9itus.dev/doc/WEBMCP.md                              doc/
cp /Volumes/PRO-BLADE/Github/u9itus.dev/doc/WEBMCP_SUBMISSION.md                   doc/
cp /Volumes/PRO-BLADE/Github/u9itus.dev/LICENSE                                    ./

# add the route snippet as a standalone reference file
#   (copy the /api/v1/mcp group out of routes/api.php into routes/webmcp.php)

git add -A
git commit -m "u9itus WebMCP civic-agent tools (WebMCP Challenge submission)"
gh repo create u9itus-webmcp --public --source=. --push
```

The README should say plainly: *"This is the WebMCP integration extracted from the
production u9itus.dev codebase (private). It runs live at
https://www.u9itus.com/webmcp"* — judges accept an extract as long as it's the
real, readable code and the live URL works.

### Option B — make `jhead12/u9itus.dev` public

Only if you're genuinely fine exposing everything. If you go this way:
`gh repo edit jhead12/u9itus.dev --visibility public --accept-visibility-change-consequences`
first do a pass for secrets (`git log -p | grep -i -E 'key|secret|token|password'`)
— note `.env*` files are already git-ignored, but check commit history.

---

## 3.5. robots.txt — agents were being blocked (fixed, needs deploy)

`public/robots.txt` had `User-agent: ChatGPT-User` → `Disallow: /` plus
`Disallow: /api` for all bots. ChatGPT (and any robots-respecting agent) refused
to fetch `/webmcp` at all — "Failed to fetch restricted URL". A judge testing in
ChatGPT browsing mode would hit the same wall.

Fixed in [public/robots.txt](../public/robots.txt): interactive agents
(`ChatGPT-User`, `OAI-SearchBot`, `Claude-Web`, `anthropic-ai`, `PerplexityBot`)
now get `Allow: /webmcp` + `Allow: /api/v1/mcp/` before their `Disallow: /`;
training crawlers (`GPTBot`, `CCBot`, `Bytespider`, …) stay fully blocked.

**Must be deployed before submitting.** After deploy:

```bash
curl -s https://www.u9itus.com/robots.txt | grep -A2 "ChatGPT-User"
```

Note: [routes/standalone.php:857](../routes/standalone.php#L857) also defines a
`/robots.txt` route, but the static `public/robots.txt` is served first and wins.
The route is dead code for this path — ignore it or delete it later.

## 4. License — done

[LICENSE](../LICENSE) (MIT) is committed at the repo root and matches the
`"license": "MIT"` already declared in `composer.json`. Copy it into the Option A
repo too.

---

## 5. Demo video — under 3 minutes

Record screen + voice. Keep it to ~2:30. Suggested shot list:

| Time | Shot | Say |
|---|---|---|
| 0:00–0:20 | `/webmcp` page loading; badge flips to **connected** | "u9itus is a civic-transparency platform. It's now a WebMCP provider — any AI agent can call its research tools directly, no scraping." |
| 0:20–0:35 | Scroll the tool catalogue table | "Seven tools: search candidates, pull a full dossier, compare candidates, ballot measures, elections, and a human-in-the-loop lead submission." |
| 0:35–1:30 | In a WebMCP agent (ChatGPT browser / Chrome+WebMCP) on `/webmcp`, prompt: *"Use u9itus to find who represents Ohio's 8th district, then pull their full dossier."* Show the agent calling `u9itus_find_candidates` then `u9itus_get_candidate`. | "The agent picks the right tool, passes the uuid through, and gets structured data back — transparency IDs, verified news, donor snapshot." |
| 1:30–2:05 | Prompt: *"Compare that candidate with another Ohio representative."* Show `u9itus_compare_candidates`. | "Side-by-side, straight from the same verified source the website uses." |
| 2:05–2:30 | Show the live console on `/webmcp` running the same endpoint manually; then the `doc/WEBMCP.md` architecture diagram. | "Same JSON API behind the tools and the site. It's small, it's MIT-licensed, and it turns every civic-data site into something an agent can reason over." |

**Before recording:** run the console forms on `/webmcp` and confirm each returns
data. `elections?state=OH` currently returns an empty list — use a state/candidate
combo you've verified returns results, or use the find → get → compare flow
(which definitely has data). Upload to YouTube as **Public** or **Unlisted is not
allowed — must be Public**.

---

## 6. Devpost form — field by field

Go to <https://webmcp.devpost.com/> → **Submit a project**.

- **Project name:** `u9itus — WebMCP civic-research agent`
- **Tagline:** *Any AI agent can research U.S. candidates, elections, and ballot measures directly against verified civic data — via WebMCP, no scraping.*
- **"What it does":** paste §2's table + the one-paragraph pitch.
- **"How I built it":** Laravel 12 API (`/api/v1/mcp/*`) over an existing
  789-profile civic dataset; a ~270-line browser module registers the tools via
  `navigator.modelContext` / `document.modelContext` (handles all three known
  registration shapes) and lazy-loads so pages with no agent pay nothing;
  per-page context injected via `window.__U9ITUS_MCP__`.
- **"Challenges / what's next":** Chrome MV3 extension using the built-in Prompt
  API for a browse-anywhere civic assistant; write tools gated behind voter auth;
  FEC / Google Civic / Congress.gov data integrations.
- **"Built with" tags:** `webmcp`, `laravel`, `php`, `javascript`, `model-context-protocol`, `civic-tech`
- **Testing instructions for judges** (put this in the description — critical):

  > **Live:** <https://www.u9itus.com/webmcp>
  > 1. Open the URL in ChatGPT's browser, or Chrome with WebMCP enabled. The
  >    badge flips to "connected" when the 7 tools register.
  > 2. Ask: *"Use u9itus to find who's running for US Senate in Ohio,"* then
  >    *"pull the u9itus dossier for that candidate and compare with another."*
  > 3. No agent handy? The **Live console** on the same page calls the identical
  >    JSON endpoints. Example: `GET /api/v1/mcp/candidates?q=warren&limit=3`.

- **Links:** live URL, the **public** repo from §3, the video from §5.
- **Image / thumbnail:** screenshot of `/webmcp` with the "connected" badge.

---

## 7. Judging criteria — what to emphasize

| Criterion | Our argument |
|---|---|
| **WebMCP Leverage** | Tools are the whole interface — the agent never touches the DOM. Registration handles all three `modelContext` shapes; page context is fed structurally. Read + a safe human-in-the-loop write tool. |
| **Execution** | Live on a real production site with a real 789-profile dataset, not a toy. Rate-limited, validated, 404-guarded. Lazy-loaded so it's zero-cost when no agent is present. Fallback console proves the endpoints. |
| **Potential Impact** | Civic information is high-stakes and hard to search well. Making it agent-native — verified, sourced, comparable — is a template any government-data or transparency site can copy. |

---

## 8. Pre-submit smoke test

```bash
curl -s -o /dev/null -w "webmcp page: %{http_code}\n" https://www.u9itus.com/webmcp
curl -s "https://www.u9itus.com/api/v1/mcp/candidates?q=warren&limit=3" | jq '.count'
curl -s "https://www.u9itus.com/api/v1/mcp/candidates/ae7aa446-0b9b-4d34-ae5b-6c0bbbc9abb6" | jq '.full_name'
# public repo reachable while logged out:
curl -s -o /dev/null -w "repo: %{http_code}\n" https://github.com/jhead12/u9itus-webmcp
```

All four should succeed. Then do one real agent run end to end.

---

## 9. After you submit

- **Freeze the live URL and the public repo** until judging closes — don't push
  breaking changes to `master` that would take `/webmcp` or the API down.
- Keep the Railway `web` service running (don't scale to zero).
- You can keep editing the Devpost entry up to the deadline, not after.

---

## 10. Final order of operations (today)

1. **§3** — create `u9itus-webmcp` public repo, push. *(~20 min)*
2. **§8** — run the smoke test. *(~5 min)*
3. **§5** — record + upload the video. *(~40 min incl. retakes)*
4. **§6** — fill in the Devpost form, paste links. *(~20 min)*
5. Submit. Confirm you get the confirmation email. *(before 12:30 PM PDT)*
