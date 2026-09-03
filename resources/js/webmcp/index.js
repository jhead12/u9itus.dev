/**
 * u9itus WebMCP civic-agent tools
 * ================================
 * https://github.com/webmachinelearning/webmcp
 *
 * Registers a small catalogue of civic tools with whatever WebMCP
 * implementation the browser exposes (native `navigator.modelContext`,
 * a polyfill, or an extension). An AI agent browsing u9itus.dev can then
 * call these directly instead of scraping the DOM:
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
 */

const API_BASE = "/api/v1/mcp";
const TOOL_PREFIX = "u9itus_";

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
    if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, v);
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
        "Search u9itus published candidate and elected-official profiles by name and/or filters. Use for questions like 'who is running for X in Y' or to resolve a name mentioned in an article to a u9itus profile (each result has a uuid for u9itus_get_candidate).",
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
          limit: { type: "integer", minimum: 1, maximum: 20 },
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
        "List US ballot measures (always state/county scoped) with plain-language yes/no meanings. Filter by state, free-text, and status (upcoming|passed|failed). If a state has none yet, the response includes a `backfill` block (status queued|in_progress|unavailable) — relay its message and offer u9itus_watch_ballot_measures.",
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

/**
 * Register with whatever WebMCP surface exists. Returns true if a
 * registration path was found.
 */
function tryRegister(tools) {
  const ctx =
    (typeof navigator !== "undefined" && navigator.modelContext) ||
    (typeof window !== "undefined" && window.modelContext) ||
    (typeof document !== "undefined" && document.modelContext) ||
    null;

  if (!ctx) return false;

  // Shape A — imperative per-tool registration (current spec draft).
  if (typeof ctx.registerTool === "function") {
    tools.forEach((tool) => {
      try {
        ctx.registerTool(tool);
      } catch (e) {
        /* one bad tool shouldn't abort the rest */
      }
    });
    return true;
  }

  // Shape B — declarative bulk registration (earlier polyfills).
  if (typeof ctx.provideContext === "function") {
    try {
      ctx.provideContext({ tools });
      return true;
    } catch (e) {
      return false;
    }
  }

  // Shape C — a plain tools array.
  if (Array.isArray(ctx.tools)) {
    ctx.tools.push(...tools);
    return true;
  }

  return false;
}

export function registerCivicTools() {
  if (window.__U9ITUS_MCP_REGISTERED__ || window.__U9ITUS_MCP_DISABLED__) return;

  let tools;
  try {
    tools = toolDefinitions();
  } catch (e) {
    return;
  }

  const attempt = () => {
    try {
      if (tryRegister(tools)) {
        window.__U9ITUS_MCP_REGISTERED__ = true;
        window.dispatchEvent(new CustomEvent("u9itus:webmcp-ready", { detail: { count: tools.length } }));
        return true;
      }
    } catch (e) {
      /* swallow — never break the host page */
    }
    return false;
  };

  if (attempt()) return;

  // The API may be injected late by an extension / polyfill. Retry for ~10s,
  // and also react to the common readiness signals.
  let tries = 0;
  const timer = setInterval(() => {
    tries += 1;
    if (attempt() || tries >= 20) clearInterval(timer);
  }, 500);

  ["modelcontextready", "modelcontext-ready", "DOMContentLoaded"].forEach((evt) =>
    window.addEventListener(evt, attempt, { once: true }),
  );
}

export default registerCivicTools;
