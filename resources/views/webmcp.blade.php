<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WebMCP Civic Agent — U9itus</title>
    <meta name="description" content="U9itus exposes civic-research tools to AI agents via WebMCP: find candidates, pull a dossier, compare candidates, list ballot measures, look up elections, and submit candidate leads.">
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <script>
        window.__U9ITUS_MCP__ = { pageType: 'webmcp_demo' };
    </script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        pre { white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen antialiased">

<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">

    <a href="{{ url('/') }}" class="text-sm text-slate-400 hover:text-white transition">&larr; u9itus.dev</a>

    <header class="mt-6 mb-10">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                <span class="text-slate-500 font-light"> · WebMCP</span>
            </h1>
            <span id="mcp-status"
                  class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                agent API: checking…
            </span>
        </div>
        <p class="mt-4 text-slate-300 max-w-2xl leading-relaxed">
            This page registers a set of <a class="text-emerald-400 underline" href="https://github.com/webmachinelearning/webmcp" target="_blank" rel="noopener">WebMCP</a>
            tools so an AI agent (ChatGPT's browser, or Chrome with WebMCP enabled) can do civic research
            directly against u9itus data — no DOM scraping. The same JSON endpoints power the live console below.
        </p>
        <button id="guide-open"
                class="mt-5 inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-4 py-2 transition">
            <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4" aria-hidden="true">
                <path d="M10 2a1 1 0 0 1 1 1v1.06a6 6 0 0 1 4.88 4.88H17a1 1 0 1 1 0 2h-1.12a6 6 0 0 1-4.88 4.88V17a1 1 0 1 1-2 0v-1.3a6 6 0 0 1-4.88-4.88H3a1 1 0 1 1 0-2h1.06A6 6 0 0 1 9 4.06V3a1 1 0 0 1 1-1Zm0 5a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
            </svg>
            Set up your browser
        </button>
    </header>

    <section class="mb-12">
        <h2 class="text-lg font-bold text-white mb-4">Tool catalogue</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800">
            <table class="w-full text-sm">
                <thead class="bg-slate-900 text-slate-400 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tool</th>
                        <th class="px-4 py-3 font-semibold">What it does</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_current_page</td><td class="px-4 py-3 text-slate-300">Describes the u9itus page in view (and the candidate in focus).</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_find_candidates</td><td class="px-4 py-3 text-slate-300">Searches published candidate / official profiles by name and filters.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_get_candidate</td><td class="px-4 py-3 text-slate-300">Full civic dossier: office, party, transparency IDs, verified news, donors, elections.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_compare_candidates</td><td class="px-4 py-3 text-slate-300">Side-by-side dossiers for 2–4 candidates.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_list_ballot_measures</td><td class="px-4 py-3 text-slate-300">State / county ballot measures with plain-language yes/no meanings.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_watch_ballot_measures</td><td class="px-4 py-3 text-slate-300">Register an email to be notified when a state's ballot measures are published.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_upcoming_elections</td><td class="px-4 py-3 text-slate-300">Election stages and filing deadlines for a state.</td></tr>
                    <tr><td class="px-4 py-3 font-mono text-emerald-300">u9itus_submit_candidate_lead</td><td class="px-4 py-3 text-slate-300">Queues a spotted candidate for human verification. Never publishes.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-12">
        <h2 class="text-lg font-bold text-white mb-4">Live console</h2>
        <p class="text-slate-400 text-sm mb-5">Calls the same endpoints the tools use. Try <code class="text-emerald-300">find candidates</code> first, copy a <code class="text-emerald-300">uuid</code> into the dossier field.</p>

        <div class="grid sm:grid-cols-2 gap-4">
            <form data-endpoint="/api/v1/mcp/candidates" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
                <div class="font-semibold text-white text-sm">find_candidates</div>
                <input name="q" placeholder="name (e.g. Warren)" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <input name="state" placeholder="state (e.g. MA)" maxlength="2" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-3 py-2 transition">Run</button>
            </form>

            <form data-endpoint="/api/v1/mcp/candidates/:uuid" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
                <div class="font-semibold text-white text-sm">get_candidate</div>
                <input name="uuid" placeholder="candidate uuid" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-3 py-2 transition">Run</button>
            </form>

            <form data-endpoint="/api/v1/mcp/ballot-measures" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
                <div class="font-semibold text-white text-sm">list_ballot_measures</div>
                <input name="state" placeholder="state (e.g. CA)" maxlength="2" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <input name="q" placeholder="text match (optional)" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-3 py-2 transition">Run</button>
            </form>

            <form data-endpoint="/api/v1/mcp/elections" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
                <div class="font-semibold text-white text-sm">upcoming_elections</div>
                <input name="state" placeholder="state (e.g. TX)" maxlength="2" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-3 py-2 transition">Run</button>
            </form>

            <form data-endpoint="/api/v1/mcp/ballot-measures/watch" data-method="post" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
                <div class="font-semibold text-white text-sm">watch_ballot_measures</div>
                <input name="state" placeholder="state (e.g. TX)" maxlength="2" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <input name="email" type="email" placeholder="notify email" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm">
                <button class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-3 py-2 transition">Run</button>
            </form>
        </div>

        <div class="mt-5">
            <div class="text-xs text-slate-500 mb-1">Response</div>
            <pre id="console-out" class="bg-black/60 border border-slate-800 rounded-xl p-4 text-xs text-emerald-200 min-h-[6rem] overflow-x-auto">—</pre>
        </div>
    </section>

    <section class="mb-16">
        <h2 class="text-lg font-bold text-white mb-4">Testing with an agent</h2>
        <ol class="list-decimal list-inside text-slate-300 text-sm space-y-2 leading-relaxed">
            <li>Open this site in Chrome 149+ with WebMCP, or with a WebMCP extension (e.g. Rook). The tools register on <code class="text-emerald-300">document.modelContext</code>.</li>
            <li>The badge above flips to <span class="text-emerald-400 font-semibold">connected</span> — and reports which surface bound and how many tools the agent can actually see.</li>
            <li>Ask the agent things like <em>"use u9itus to find who's running for US Senate in Ohio"</em> or <em>"pull the u9itus dossier for that candidate and compare with their opponent."</em></li>
        </ol>

        <div class="mt-5 rounded-xl border border-slate-800 bg-slate-900 p-4">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-semibold text-white text-sm">No agent handy?</div>
                    <p class="text-slate-400 text-xs mt-1 max-w-xl">Install a minimal in-page <code class="text-emerald-300">document.modelContext</code>, then discover and invoke the real registered tools — the exact code path a WebMCP agent uses.</p>
                </div>
                <button id="sim-install" class="shrink-0 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-4 py-2 transition">Simulate an agent</button>
            </div>
            <div id="sim-panel" class="mt-4 hidden">
                <div class="grid sm:grid-cols-[1fr_auto] gap-3">
                    <select id="sim-tool" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-sm font-mono text-emerald-200"></select>
                    <button id="sim-run" class="bg-slate-700 hover:bg-slate-600 text-white text-sm font-semibold rounded-lg px-4 py-2 transition">Run tool</button>
                </div>
                <textarea id="sim-args" rows="3" class="mt-3 w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs font-mono text-emerald-200" placeholder='{ "q": "warren", "limit": 3 }'></textarea>
            </div>
        </div>
    </section>

    <footer class="text-xs text-slate-600 border-t border-slate-800 pt-6">
        WebMCP tool source: <code>resources/js/webmcp/index.js</code> · backend: <code>App\Http\Controllers\Api\WebMcpController</code> · docs: <code>doc/WEBMCP.md</code>
    </footer>
</div>

{{-- ───────────────────────── Browser setup guide (modal) ───────────────────────── --}}
<div id="guide-modal"
     class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/70 backdrop-blur-sm px-4 py-8 sm:py-12"
     role="dialog" aria-modal="true" aria-labelledby="guide-title">
    <div id="guide-card"
         class="w-full max-w-2xl rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-4">
            <div>
                <h2 id="guide-title" class="text-lg font-bold text-white">Set up your browser for the AI tools</h2>
                <p class="mt-1 text-xs text-slate-400">
                    WebMCP lets an AI agent in your browser call u9itus civic tools directly. Pick whichever route matches your browser.
                </p>
            </div>
            <button id="guide-close"
                    class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white transition"
                    aria-label="Close">
                <svg viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                </svg>
            </button>
        </div>

        <div class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-5 text-sm leading-relaxed text-slate-300">

            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-300 border border-emerald-500/40">Option A</span>
                    <h3 class="font-semibold text-white">Chrome's built-in AI agent (Chrome 149+)</h3>
                </div>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5 marker:text-slate-500">
                    <li>Update Chrome to version 149 or newer — check at <code class="text-emerald-300">chrome://settings/help</code>, then relaunch.</li>
                    <li>Open <code class="text-emerald-300">chrome://flags</code> in a new tab, search for <span class="text-white">model context</span> (or <span class="text-white">WebMCP</span>), and set the <span class="text-white">Web Model Context API</span> flag to <span class="text-white">Enabled</span>.</li>
                    <li>Click <span class="text-white">Relaunch</span>.</li>
                    <li>Open Chrome's AI assistant panel and start an agent session, then switch back to this tab.</li>
                </ol>
                <p class="mt-2 text-xs text-slate-500">Flag names move around between Chrome versions — if you don't see an exact match, enable the closest "Model Context" / "WebMCP" entry.</p>
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-300 border border-emerald-500/40">Option B</span>
                    <h3 class="font-semibold text-white">A WebMCP browser extension (any Chromium browser)</h3>
                </div>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5 marker:text-slate-500">
                    <li>Install a WebMCP-capable extension from the Chrome Web Store — for example <span class="text-white">Rook</span>.</li>
                    <li>Pin the extension, open its side panel, and connect your AI model or sign in.</li>
                    <li>Reload this page. When the extension attaches its agent surface, the tools register automatically.</li>
                </ol>
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-300 border border-emerald-500/40">Option C</span>
                    <h3 class="font-semibold text-white">An agentic browser</h3>
                </div>
                <p class="mt-3">
                    Browsers with a built-in browsing agent (ChatGPT's browser / Atlas and similar) discover these tools automatically when the agent
                    visits the page — no setup. Just ask it to <em>"use u9itus to …"</em>.
                </p>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <h3 class="font-semibold text-white">No agent right now?</h3>
                <p class="mt-1 text-slate-400">
                    Close this and use <span class="text-white">Simulate an agent</span> lower down the page — it installs a minimal in-page
                    <code class="text-emerald-300">document.modelContext</code> and lets you call the real registered tools yourself.
                </p>
            </div>

            <div>
                <h3 class="font-semibold text-white">How to tell it worked</h3>
                <p class="mt-1">
                    The badge next to the page title flips from <span class="text-slate-400">"agent API: checking…"</span> to a green
                    <span class="font-semibold text-emerald-300">agent API: connected</span> — with the surface it bound to and the number of tools your agent can see.
                </p>
            </div>

            <div>
                <h3 class="font-semibold text-white">Then just ask</h3>
                <ul class="mt-2 list-disc space-y-1.5 pl-5 marker:text-slate-600">
                    <li><em>"use u9itus to find who's running for US Senate in Ohio"</em></li>
                    <li><em>"pull the u9itus dossier for that candidate and compare with their opponent"</em></li>
                    <li><em>"list the California ballot measures and explain what a yes vote means"</em></li>
                </ul>
            </div>

            <div>
                <h3 class="font-semibold text-white">Privacy</h3>
                <p class="mt-1 text-slate-400">
                    The read tools call u9itus's public civic JSON endpoints (rate-limited). The two write tools — submitting a candidate lead and
                    watching a state's ballot measures — queue for human review and never publish; a well-behaved agent will confirm with you first.
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-800 px-6 py-4">
            <a href="https://github.com/webmachinelearning/webmcp" target="_blank" rel="noopener"
               class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white transition">About WebMCP ↗</a>
            <button id="guide-done"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 transition">Got it</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var statusEl = document.getElementById('mcp-status');
        var out = document.getElementById('console-out');

        function badge(text, cls) {
            statusEl.textContent = text;
            statusEl.className = 'text-xs font-semibold px-2.5 py-1 rounded-full ' + cls;
        }
        function onReady(e) {
            var d = (e && e.detail) || window.__U9ITUS_MCP_STATE__ || {};
            var n = d.visible != null ? d.visible : (d.registered != null ? d.registered : (d.tools ? d.tools.length : '?'));
            badge('agent API: connected · ' + (d.surface || '?') + '.' + (d.shape || '?') + ' · ' + n + ' tools',
                'bg-emerald-500/15 text-emerald-300 border border-emerald-500/40');
        }
        window.addEventListener('u9itus:webmcp-ready', onReady);
        if (window.__U9ITUS_MCP_REGISTERED__) onReady();
        setTimeout(function () {
            if (!window.__U9ITUS_MCP_REGISTERED__) {
                badge('agent API: idle — open your WebMCP agent, or use “Simulate an agent” below',
                    'bg-slate-800 text-slate-400 border border-slate-700');
            }
        }, 12000);

        document.querySelectorAll('form[data-endpoint]').forEach(function (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                var tmpl = form.getAttribute('data-endpoint');
                var method = (form.getAttribute('data-method') || 'get').toUpperCase();
                var fd = new FormData(form);
                var url, opts = { headers: { Accept: 'application/json' } };
                if (tmpl.indexOf(':uuid') !== -1) {
                    var uuid = (fd.get('uuid') || '').trim();
                    if (!uuid) { out.textContent = 'Enter a uuid.'; return; }
                    url = new URL(tmpl.replace(':uuid', encodeURIComponent(uuid)), window.location.origin);
                } else if (method === 'POST') {
                    url = new URL(tmpl, window.location.origin);
                    var payload = {};
                    fd.forEach(function (v, k) { if (v) payload[k] = v; });
                    opts.method = 'POST';
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(payload);
                } else {
                    url = new URL(tmpl, window.location.origin);
                    fd.forEach(function (v, k) { if (v) url.searchParams.set(k, v); });
                }
                out.textContent = 'Loading…';
                try {
                    var res = await fetch(url, opts);
                    var body = await res.json();
                    out.textContent = JSON.stringify(body, null, 2);
                } catch (err) {
                    out.textContent = 'Request failed: ' + err;
                }
            });
        });

        /* ---- Simulate an agent: minimal document.modelContext, then drive the real tools ---- */
        var installBtn = document.getElementById('sim-install');
        var panel = document.getElementById('sim-panel');
        var toolSel = document.getElementById('sim-tool');
        var argsBox = document.getElementById('sim-args');
        var runBtn = document.getElementById('sim-run');

        function makeSurface() {
            var reg = [];
            var et = new EventTarget();
            var api = {
                __u9itusSim: true,
                registerTool: function (tool) {
                    var i = reg.findIndex(function (t) { return t.name === tool.name; });
                    if (i >= 0) { reg[i] = tool; } else { reg.push(tool); }
                    et.dispatchEvent(new Event('toolchange'));
                    return Promise.resolve();
                },
                unregisterTool: function (name) {
                    reg = reg.filter(function (t) { return t.name !== name; });
                    et.dispatchEvent(new Event('toolchange'));
                    return Promise.resolve();
                },
                getTools: function () {
                    return Promise.resolve(reg.map(function (t) {
                        return { name: t.name, description: t.description, inputSchema: t.inputSchema };
                    }));
                },
                executeTool: function (tool, args) {
                    var name = tool && tool.name ? tool.name : tool;
                    var impl = reg.find(function (t) { return t.name === name; });
                    if (!impl) { return Promise.reject(new Error('unknown tool: ' + name)); }
                    return Promise.resolve(impl.execute(args || {}));
                }
            };
            ['addEventListener', 'removeEventListener', 'dispatchEvent'].forEach(function (m) {
                api[m] = et[m].bind(et);
            });
            return api;
        }

        async function refreshTools() {
            var list = [];
            try { list = await document.modelContext.getTools(); } catch (e) { list = []; }
            toolSel.innerHTML = '';
            list.forEach(function (t) {
                var o = document.createElement('option');
                o.value = t.name;
                o.textContent = t.name;
                toolSel.appendChild(o);
            });
            return list.length;
        }

        if (installBtn) {
            installBtn.addEventListener('click', function () {
                if (!document.modelContext) {
                    document.modelContext = makeSurface();
                } else if (!document.modelContext.__u9itusSim) {
                    out.textContent = 'A real agent surface is already present on document.modelContext — test with that instead.';
                    return;
                }
                window.dispatchEvent(new Event('modelcontextready'));
                panel.classList.remove('hidden');
                installBtn.disabled = true;
                installBtn.textContent = 'Simulator installed';

                var tries = 0;
                var poll = setInterval(async function () {
                    var n = await refreshTools();
                    if (n > 0 || ++tries > 12) {
                        clearInterval(poll);
                        out.textContent = n > 0
                            ? 'Registered ' + n + ' tools on a simulated document.modelContext.\nPick one, edit the JSON args, and Run tool.'
                            : 'No tools registered — the WebMCP module did not load on this page.';
                    }
                }, 400);
            });
        }

        if (runBtn) {
            runBtn.addEventListener('click', async function () {
                var name = toolSel.value;
                if (!name) { return; }
                var args = {};
                var raw = argsBox.value.trim();
                if (raw) {
                    try { args = JSON.parse(raw); }
                    catch (e) { out.textContent = 'Args must be valid JSON: ' + e; return; }
                }
                out.textContent = 'Calling ' + name + '…';
                try {
                    var r = await document.modelContext.executeTool({ name: name }, args);
                    var text = r && r.content && r.content[0] ? r.content[0].text : JSON.stringify(r, null, 2);
                    out.textContent = text;
                } catch (e) {
                    out.textContent = 'Tool failed: ' + e;
                }
            });
        }
    })();
</script>

<script>
    /* ---- Browser setup guide modal ---- */
    (function () {
        var modal = document.getElementById('guide-modal');
        var card = document.getElementById('guide-card');
        var openBtn = document.getElementById('guide-open');
        var SEEN_KEY = 'u9itus_webmcp_guide_seen';

        if (!modal || !openBtn) { return; }

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
            try { localStorage.setItem(SEEN_KEY, '1'); } catch (e) { /* storage blocked */ }
        }

        openBtn.addEventListener('click', open);
        ['guide-close', 'guide-done'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('click', close); }
        });
        modal.addEventListener('click', function (e) {
            if (!card.contains(e.target)) { close(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) { close(); }
        });

        // Show once automatically so first-time visitors see how to connect.
        var seen = false;
        try { seen = localStorage.getItem(SEEN_KEY) === '1'; } catch (e) { seen = false; }
        if (!seen) { setTimeout(open, 600); }
    })();
</script>
</body>
</html>
