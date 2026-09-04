/**
 * u9itus WebMCP civic-agent tools
 * ================================
 * https://github.com/webmachinelearning/webmcp
 *
 * Registers a small catalogue of civic tools with whatever WebMCP
 * implementation the browser exposes (Chrome's native `document.modelContext`,
 * a polyfill, or an extension such as Rook). An AI agent browsing u9itus.dev
 * can then call these directly instead of scraping the DOM:
 *
 *   u9itus_find_candidates       — search published candidates / officials
 *   u9itus_get_candidate         — full civic dossier for one candidate
 *   u9itus_compare_candidates    — side-by-side dossiers for 2–4 candidates
 *   u9itus_list_ballot_measures  — state / county ballot measures
 *   u9itus_watch_ballot_measures — email me when a state's measures are published
 *   u9itus_upcoming_elections    — election stages + filing deadlines by state
 *   u9itus_submit_candidate_lead — queue a spotted candidate for human review
 *   u9itus_current_page          — what u9itus page the user is looking at
 *
 * The tools call the JSON endpoints in routes/api.php (prefix v1/mcp),
 * backed by App\Http\Controllers\Api\WebMcpController.
 *
 * Loaded lazily from resources/js/app.js; every failure path is a no-op so
 * this can never break a page that has no agent attached.
 *
 * Registration notes
 * ------------------
 * The W3C draft surface is `document.modelContext` and `registerTool()` is
 * **async** — it returns a promise that can reject (permissions policy, a
 * schema the UA won't accept, an extension that hasn't finished attaching).
 * We therefore await every call, only report "ready" once tools are actually
 * present, re-assert on `toolchange`, and keep sweeping for a few minutes
 * because an extension's agent session often starts well after page load.
 */

const API_BASE = "/api/v1/mcp";
const TOOL_PREFIX = "u9itus_";

/** Keep re-checking for an agent surface this long after load (ms). */
const RETRY_WINDOW_MS = 180000;
/** Poll cadence while sweeping for a surface (ms). */
const RETRY_INTERVAL_MS = 750;

/** MCP tool-result envelope. */
function textResult(payload) {
  return {
    content: [
      {
        type: "text",
        text: typeof payload === "string" ? payload : JSON.stringify(payload, null, 2),
      },
    ],
  };
}

async function apiGet(path, params = {}) {
  const url = new URL(API_BASE + path, window.location.origin);
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === null || v === "") return;
    // Booleans must go on the wire as 1/0 — Laravel's `boolean` rule rejects "true"/"false".
    let value = v;
    if (typeof v === "boolean") value = v ? "1" : "0";
    url.searchParams.set(k, value);
  });
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    return textResult({ error: true, status: res.status, ...body });
  }
  return textResult(body);
}

async function apiPost(path, data) {
  const res = await fetch(API_BASE + path, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(data),
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    return textResult({ error: true, status: res.status, ...body });
  }
  return textResult(body);
}

/** Page context injected by the Blade layout (optional). */
function pageContext() {
  const ctx = window.__U9ITUS_MCP__ || {};
  return {
    site: "u9itus.dev",
    description:
      "u9itus is a virtual town hall: find who is running in a US district, verify their record against public data (FEC, OpenSecrets, Ballotpedia, Vote Smart), and get paid to watch candidate messages.",
    url: window.location.href,
    page_type: ctx.pageType || "unknown",
    candidate: ctx.candidate || null,
    state: ctx.state || null,
  };
}

function toolDefinitions() {
  return [
    {
      name: `${TOOL_PREFIX}current_page`,
      description:
        "Describe the u9itus.dev page the user is currently viewing (page type, and the candidate in focus if any). Call this first to ground follow-up civic questions.",
      inputSchema: { type: "object", properties: {}, additionalProperties: false },
      execute: async () => textResult(pageContext()),
    },
    {
      name: `${TOOL_PREFIX}find_candidates`,
      description:
        "Search u9itus published candidate and elected-official profiles by name and/or filters. Use for questions like 'who is running for X in Y', 'Texas candidates funded by AIPAC', or to resolve a name mentioned in an article to a u9itus profile (each result has a uuid for u9itus_get_candidate). The response includes `total` (all matches), `count` (this page), and `next_offset` — to get every match, keep calling with `offset: next_offset` until it is null. When `funded_by` is set, each result also carries a `funding_match` array citing the specific contributors / PACs and dollar amounts.",
      inputSchema: {
        type: "object",
        properties: {
          q: { type: "string", description: "Name or partial name to search for." },
          state: { type: "string", description: "Two-letter US state code, e.g. CA." },
          governance_level: {
            type: "string",
            description: "federal | state | county | municipal (as stored on the profile).",
          },
          party: { type: "string", description: "Party affiliation, partial match." },
          running: { type: "boolean", description: "true = only active candidates; false = only seated officials." },
          funded_by: {
            type: "string",
            description:
              "Keep only candidates whose FEC / OpenSecrets donor snapshot shows money from this source — a PAC, committee, advocacy group (e.g. 'AIPAC'), industry, or contributor name. Naming a known advocacy group also matches its aligned PACs. Matched results include a `funding_match` array with the named sources and amounts.",
          },
          limit: { type: "integer", minimum: 1, maximum: 20, description: "Results per page (default 10, max 20)." },
          offset: { type: "integer", minimum: 0, description: "Skip this many matches — pass the previous response's next_offset to page." },
        },
        additionalProperties: false,
      },
      execute: async (args = {}) => apiGet("/candidates", args),
    },
    {
      name: `${TOOL_PREFIX}get_candidate`,
      description:
        "Full civic dossier for one candidate by uuid: office, party, district, bio, official links, transparency IDs (FEC/OpenSecrets/Ballotpedia/Vote Smart), recent verified news, donor snapshot, and upcoming elections in their state.",
      inputSchema: {
        type: "object",
        properties: { uuid: { type: "string", description: "Candidate uuid from u9itus_find_candidates." } },
        required: ["uuid"],
        additionalProperties: false,
      },
      execute: async ({ uuid }) => apiGet(`/candidates/${encodeURIComponent(uuid)}`),
    },
    {
      name: `${TOOL_PREFIX}compare_candidates`,
      description:
        "Return full dossiers for 2–4 candidates at once (by uuid) so you can present a structured side-by-side comparison.",
      inputSchema: {
        type: "object",
        properties: {
          uuids: {
            type: "array",
            items: { type: "string" },
            minItems: 2,
            maxItems: 4,
            description: "Candidate uuids from u9itus_find_candidates.",
          },
        },
        required: ["uuids"],
        additionalProperties: false,
      },
      execute: async ({ uuids = [] }) => apiGet("/candidates/compare", { uuids: uuids.join(",") }),
    },
    {
      name: `${TOOL_PREFIX}list_ballot_measures`,
      description:
        "List US ballot measures (always state/county scoped) with plain-language yes/no meanings. Filter by state, free-text, and status (upcoming|passed|failed). Each result carries a `read_more` link ({label, url}) to the full legal text and fiscal analysis — cite it when the user asks about detail the summary omits (eligibility thresholds, dollar amounts, funding source, repayment terms). If a state has none yet, the response includes a `backfill` block (status queued|in_progress|unavailable) — relay its message and offer u9itus_watch_ballot_measures.",
      inputSchema: {
        type: "object",
        properties: {
          state: { type: "string", description: "Two-letter US state code." },
          q: { type: "string", description: "Free-text match on title or measure number." },
          status: { type: "string", enum: ["upcoming", "passed", "failed"] },
          limit: { type: "integer", minimum: 1, maximum: 20 },
        },
        additionalProperties: false,
      },
      execute: async (args = {}) => apiGet("/ballot-measures", args),
    },
    {
      name: `${TOOL_PREFIX}watch_ballot_measures`,
      description:
        "When u9itus_list_ballot_measures reports no measures for a state, register an email to be notified once that state's measures are published. Confirm the email with the user first.",
      inputSchema: {
        type: "object",
        properties: {
          state: { type: "string", description: "Two-letter US state code." },
          email: { type: "string", description: "Where to send the one-time notification." },
        },
        required: ["state", "email"],
        additionalProperties: false,
      },
      execute: async (args = {}) => apiPost("/ballot-measures/watch", args),
    },
    {
      name: `${TOOL_PREFIX}upcoming_elections`,
      description:
        "Upcoming election stages (primary, general, runoff) and candidate filing deadlines for a US state.",
      inputSchema: {
        type: "object",
        properties: { state: { type: "string", description: "Two-letter US state code, e.g. TX." } },
        required: ["state"],
        additionalProperties: false,
      },
      execute: async ({ state }) => apiGet("/elections", { state }),
    },
    {
      name: `${TOOL_PREFIX}submit_candidate_lead`,
      description:
        "Report a candidate you spotted (e.g. named in an article the user is reading) who may be missing from u9itus. This does NOT publish anything — the lead is queued as 'pending' for human verification. Always confirm with the user before calling.",
      inputSchema: {
        type: "object",
        properties: {
          full_name: { type: "string", description: "Candidate's full name." },
          state: { type: "string", description: "Two-letter US state code, if known." },
          office_hint: { type: "string", description: "Office they are seeking, e.g. 'US House NC-06'." },
          source_url: { type: "string", description: "URL of the article / page the candidate was found on." },
          context: { type: "string", description: "Short quote or summary giving evidence (max ~2000 chars)." },
        },
        required: ["full_name", "source_url"],
        additionalProperties: false,
      },
      execute: async (args = {}) => apiPost("/candidate-leads", args),
    },
  ];
}

/* ----------------------------------------------------------------------
 | Registration
 * -------------------------------------------------------------------- */

/** Every WebMCP-ish surface currently on the page, most-canonical first. */
function surfaces() {
  const out = [];
  if (typeof document !== "undefined" && document.modelContext) out.push(["document", document.modelContext]);
  if (typeof navigator !== "undefined" && navigator.modelContext) out.push(["navigator", navigator.modelContext]);
  if (typeof window !== "undefined" && window.modelContext) out.push(["window", window.modelContext]);
  return out;
}

/** How many u9itus tools a surface currently reports, if it supports discovery. */
async function visibleToolCount(ctx) {
  if (typeof ctx.getTools !== "function") return null;
  try {
    const all = await ctx.getTools();
    return (Array.isArray(all) ? all : []).filter((t) => t && typeof t.name === "string" && t.name.startsWith(TOOL_PREFIX)).length;
  } catch (e) {
    return null;
  }
}

/**
 * Register the catalogue on one surface. Returns a summary object on success
 * (at least one tool registered), or null. Handles the three shapes seen in
 * the wild: imperative `registerTool` (spec), declarative `provideContext`,
 * and a plain `tools` array.
 */
async function registerOn(label, ctx, tools) {
  // Shape A — imperative per-tool registration (W3C draft). ASYNC — await it.
  if (typeof ctx.registerTool === "function") {
    let registered = 0;
    for (const tool of tools) {
      try {
        await ctx.registerTool(tool);
        registered += 1;
      } catch (e) {
        console.warn(`[u9itus webmcp] ${label}.registerTool(${tool.name}) rejected:`, e);
      }
    }
    if (registered === 0) return null;

    // Nudge agents that attached their listener after we registered.
    try {
      ctx.dispatchEvent?.(new Event("toolchange"));
    } catch (e) {
      /* not all surfaces are EventTargets */
    }

    const visible = await visibleToolCount(ctx);
    return { shape: "registerTool", registered, visible: visible ?? registered };
  }

  // Shape B — declarative bulk registration (replaces the whole set).
  if (typeof ctx.provideContext === "function") {
    try {
      await ctx.provideContext({ tools });
      const visible = await visibleToolCount(ctx);
      return { shape: "provideContext", registered: tools.length, visible: visible ?? tools.length };
    } catch (e) {
      console.warn(`[u9itus webmcp] ${label}.provideContext rejected:`, e);
      return null;
    }
  }

  // Shape C — a plain tools array.
  if (Array.isArray(ctx.tools)) {
    const have = new Set(ctx.tools.map((t) => t && t.name));
    tools.forEach((t) => {
      if (!have.has(t.name)) ctx.tools.push(t);
    });
    return { shape: "tools[]", registered: tools.length, visible: ctx.tools.length };
  }

  return null;
}

export function registerCivicTools() {
  if (typeof window === "undefined") return;
  if (window.__U9ITUS_MCP_DISABLED__ || window.__U9ITUS_MCP_INIT__) return;
  window.__U9ITUS_MCP_INIT__ = true;

  let tools;
  try {
    tools = toolDefinitions();
  } catch (e) {
    return;
  }

  const boundSurfaces = new WeakSet();
  let announced = false;

  const announce = (detail) => {
    window.__U9ITUS_MCP_REGISTERED__ = true;
    window.__U9ITUS_MCP_STATE__ = { ...detail, tools: tools.map((t) => t.name) };
    window.dispatchEvent(
      new CustomEvent("u9itus:webmcp-ready", { detail: window.__U9ITUS_MCP_STATE__ }),
    );
    announced = true;
  };

  const watchForClears = (label, ctx) => {
    try {
      ctx.addEventListener?.("toolchange", async () => {
        const visible = await visibleToolCount(ctx);
        if (visible !== null && visible < tools.length) {
          await registerOn(label, ctx, tools);
        }
      });
    } catch (e) {
      /* surface isn't an EventTarget — nothing to watch */
    }
  };

  const sweep = async () => {
    for (const [label, ctx] of surfaces()) {
      if (boundSurfaces.has(ctx)) continue;
      const result = await registerOn(label, ctx, tools);
      if (result) {
        boundSurfaces.add(ctx);
        watchForClears(label, ctx);
        announce({ surface: label, ...result });
      }
    }
    return announced;
  };

  sweep();

  const startedAt = Date.now();
  const timer = setInterval(() => {
    if (Date.now() - startedAt > RETRY_WINDOW_MS) {
      clearInterval(timer);
      return;
    }
    sweep();
  }, RETRY_INTERVAL_MS);

  // An extension / polyfill can inject its surface late, or only once the
  // user opens its agent panel. Re-sweep on the usual signals.
  const kick = () => {
    sweep();
  };
  ["modelcontextready", "modelcontext-ready", "DOMContentLoaded", "focus", "pointerdown", "keydown"].forEach((evt) =>
    window.addEventListener(evt, kick, { passive: true }),
  );
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) sweep();
  });
}

export default registerCivicTools;
