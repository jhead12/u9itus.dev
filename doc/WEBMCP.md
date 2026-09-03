# WebMCP Civic Agent

U9itus exposes a small catalogue of **civic-research tools** to AI agents using
[WebMCP](https://github.com/webmachinelearning/webmcp) — the proposed web
standard that lets a site register structured tools an agent can call directly,
instead of scraping the DOM or driving the UI.

Built for the [WebMCP Challenge](https://webmcp.devpost.com/).

## Why

U9itus already normalises candidate data, transparency IDs (FEC, OpenSecrets,
Ballotpedia, Vote Smart), verified news, donor snapshots, ballot measures and
election calendars. WebMCP turns that into a civic API an agent can use while a
person is browsing: *"who's running for Senate in Ohio"*, *"pull the dossier and
compare with the opponent"*, *"this article names a candidate we don't have —
add them"*.

## Architecture

```
Agent (ChatGPT browser / Chrome WebMCP)
        │  calls tool.execute(args)
        ▼
resources/js/webmcp/index.js      ← registers tools with navigator.modelContext
        │  fetch()
        ▼
routes/api.php  (prefix: v1/mcp, throttled)
        ▼
App\Http\Controllers\Api\WebMcpController
        ▼
Politician / CandidateNewsArticle / BallotMeasure / StateElectionDate / CandidateLead
```

- `resources/js/webmcp/index.js` — tool definitions + a registration adapter
  that supports the current `registerTool()` spec shape, the older
  `provideContext({ tools })` shape, and a plain `tools` array, and retries for
  ~10s in case a polyfill/extension injects the API late. Loaded lazily from
  `resources/js/app.js`; every failure path is a no-op.
- Page context is published as `window.__U9ITUS_MCP__` by the Blade layouts
  (`standalone/layouts/public.blade.php`) and the candidate profile view
  (`standalone/public/profile.blade.php`), so `u9itus_current_page` can tell the
  agent which candidate is on screen.
- `/webmcp` — a demo page with the tool catalogue, a live console hitting the
  same endpoints, and an "agent API detected" indicator.

## Tools

| Tool | Endpoint | Notes |
|------|----------|-------|
| `u9itus_current_page` | — | Reads `window.__U9ITUS_MCP__`. Page type + candidate in focus. |
| `u9itus_find_candidates` | `GET /api/v1/mcp/candidates` | `q, state, governance_level, party, running, limit`. Returns summaries with `uuid`. |
| `u9itus_get_candidate` | `GET /api/v1/mcp/candidates/{uuid}` | Full dossier: office, party, transparency IDs, links, recent verified news, donor snapshot, upcoming elections. |
| `u9itus_compare_candidates` | `GET /api/v1/mcp/candidates/compare?uuids=a,b` | 2–4 dossiers in one call. |
| `u9itus_list_ballot_measures` | `GET /api/v1/mcp/ballot-measures` | `state, q, status, limit`. Plain-language yes/no meanings. |
| `u9itus_upcoming_elections` | `GET /api/v1/mcp/elections?state=XX` | Stages + filing deadlines. |
| `u9itus_submit_candidate_lead` | `POST /api/v1/mcp/candidate-leads` | `full_name, source_url` required. Queues `pending` — **never publishes**. Deduped on `source_url`. Rate-limited `5/min`. |

All read endpoints are unauthenticated public civic data, rate-limited `60/min`.
Tool results use the MCP `{ content: [{ type: "text", text }] }` envelope.

## Testing

1. `npm run build` (or `npm run dev`) so `resources/js/app.js` is served.
2. Open `https://<host>/webmcp`.
3. Open the site in ChatGPT's browser, or Chrome with WebMCP enabled. The badge
   flips to **connected** when the tools register.
4. Ask the agent to use the tools, e.g.
   *"use u9itus to find who's running for governor in Georgia, then get the dossier for the first result."*

Manual endpoint check:

```bash
curl -s "https://<host>/api/v1/mcp/candidates?q=warren&limit=3" | jq
curl -s "https://<host>/api/v1/mcp/elections?state=CA" | jq
```

## Future work

- Chrome extension (MV3) that classifies any page the user is on with Chrome's
  built-in Prompt API and, when it detects a candidate/race, offers a one-click
  civic action that calls these tools cross-origin.
- Write tools behind voter auth: favourite a candidate, RSVP a civic event,
  start a "watch & earn" session.
