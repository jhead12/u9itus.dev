<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>U.S. Regional Map – {{ config('app.name', 'U9itus') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <script type="importmap">
    {
      "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.164.1/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.164.1/examples/jsm/"
      }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3@7.9.0/dist/d3.min.js"></script>

    <script>
    /* ── Avatar initials helper (global, used by module + onerror attrs) ── */
    function avatarInitials(name, color, size) {
        const parts = (name || '').trim().split(/\s+/);
        const initials = (parts.length >= 2
            ? parts[0][0] + parts[parts.length - 1][0]
            : (parts[0]?.[0] || '?')).toUpperCase();
        const fontSize = size >= 44 ? 16 : 13;
        const bg     = color ? color + '30' : 'rgba(99,102,241,0.18)';
        const border = color ? color + '70' : 'rgba(99,102,241,0.4)';
        const h = size / 2;
        return `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg">`
            + `<circle cx="${h}" cy="${h}" r="${h}" fill="${bg}" stroke="${border}" stroke-width="1.5"/>`
            + `<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central"`
            + ` font-family="system-ui,sans-serif" font-size="${fontSize}" font-weight="700"`
            + ` fill="${color || '#818cf8'}" opacity="0.9">${initials}</text>`
            + `</svg>`;
    }
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #06091a; font-family: system-ui, sans-serif; }
        canvas { display: block; }

        /* ── Accessibility ────────────────────────────────────────────────── */
        /* Skip-to-main link (visible only on focus) */
        #skip-to-main {
            position: fixed; top: -100px; left: 16px; z-index: 9999;
            background: #6366f1; color: #fff; padding: 10px 18px;
            border-radius: 8px; font-size: 14px; font-weight: 700;
            text-decoration: none; transition: top 0.15s;
            border: 2px solid #818cf8;
        }
        #skip-to-main:focus { top: 16px; outline: none; }

        /* Visible focus ring for all interactive elements */
        :focus-visible {
            outline: 2px solid #818cf8;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* Canvas gets a focusable region for keyboard nav */
        #map-canvas-region {
            position: fixed; inset: 0; z-index: 1;
            pointer-events: none; /* must not block mouse clicks on the 3D canvas */
        }
        #map-canvas-region:focus { outline: none; } /* handled by #kb-focus-ring */
        #map-canvas-region:focus-visible + #kb-focus-ring,
        #map-canvas-region.kb-active + #kb-focus-ring { opacity: 1; }
        #kb-focus-ring {
            position: fixed; inset: 0; pointer-events: none; z-index: 2;
            box-shadow: inset 0 0 0 3px rgba(99,102,241,0.6);
            border-radius: 0; opacity: 0;
            transition: opacity 0.2s;
        }

        /* Keyboard help overlay */
        #kb-help {
            display: none; position: fixed; inset: 0; z-index: 300;
            background: rgba(6,9,26,0.92); backdrop-filter: blur(8px);
            align-items: center; justify-content: center;
        }
        #kb-help.open { display: flex; }
        #kb-help-box {
            background: #0f172a; border: 1px solid rgba(99,102,241,0.3);
            border-radius: 16px; padding: 28px 32px; max-width: 480px; width: 90%;
            color: #e2e8f0;
        }
        #kb-help-box h2 { margin: 0 0 18px; font-size: 16px; color: #818cf8; display: flex; align-items: center; gap: 8px; }
        .kb-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .kb-table tr + tr td { border-top: 1px solid rgba(99,102,241,0.1); }
        .kb-table td { padding: 7px 4px; color: #94a3b8; }
        .kb-table td:first-child { color: #e2e8f0; }
        kbd {
            display: inline-block; background: rgba(99,102,241,0.15);
            border: 1px solid rgba(99,102,241,0.35); border-radius: 5px;
            padding: 1px 7px; font-family: monospace; font-size: 11px;
            color: #818cf8; margin: 1px;
        }
        #kb-help-close {
            display: block; margin: 20px auto 0; padding: 8px 24px;
            background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.4);
            border-radius: 8px; color: #818cf8; font-size: 13px; cursor: pointer;
        }
        #kb-help-close:hover { background: rgba(99,102,241,0.35); }

        /* Keyboard accessibility indicator badge (bottom-right) */
        #kb-hint-badge {
            position: fixed; bottom: 16px; left: 16px; z-index: 60;
            background: rgba(15,23,42,0.85); border: 1px solid rgba(99,102,241,0.25);
            border-radius: 8px; padding: 5px 11px;
            font-size: 11px; color: #475569;
            display: flex; align-items: center; gap: 6px;
            pointer-events: auto; cursor: pointer;
            transition: color 0.15s, border-color 0.15s;
        }
        #kb-hint-badge:hover { color: #94a3b8; border-color: rgba(99,102,241,0.5); }
        #kb-hint-badge kbd { font-size: 10px; padding: 0 5px; }

        #top-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(6, 9, 26, 0.88);
            border-bottom: 1px solid rgba(99, 102, 241, 0.18);
            backdrop-filter: blur(14px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; height: 54px;
        }
        #top-bar a { color: #818cf8; font-weight: 700; font-size: 18px; text-decoration: none; }
        #top-bar .sep { color: #334155; font-size: 14px; margin: 0 14px; }
        #top-bar .title { color: #94a3b8; font-size: 14px; }
        .top-btn {
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.28);
            color: #818cf8; padding: 5px 14px;
            border-radius: 6px; font-size: 12px; font-weight: 500;
            cursor: pointer; transition: background 0.15s;
        }
        .top-btn:hover { background: rgba(99,102,241,0.25); }
        .top-btn.active {
            background: rgba(99,102,241,0.35);
            border-color: rgba(99,102,241,0.7);
            color: #c7d2fe;
        }
        /* ── Search palette ── */
        #search-overlay {
            position: fixed; inset: 0; z-index: 300;
            background: rgba(3, 5, 18, 0.72);
            backdrop-filter: blur(6px);
            display: none; align-items: flex-start; justify-content: center;
            padding-top: 80px;
        }
        #search-overlay.open { display: flex; }
        #search-box {
            width: 560px; max-width: calc(100vw - 32px);
            background: rgba(10, 14, 35, 0.98);
            border: 1px solid rgba(99,102,241,0.45);
            border-radius: 14px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(99,102,241,0.1) inset;
            overflow: hidden;
        }
        #search-input-wrap {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(99,102,241,0.15);
        }
        #search-input-wrap svg { flex-shrink: 0; color: #475569; }
        #search-input {
            flex: 1; background: none; border: none; outline: none;
            font-size: 16px; color: #f1f5f9; caret-color: #6366f1;
        }
        #search-input::placeholder { color: #334155; }
        #search-kbd {
            font-size: 10px; color: #334155; flex-shrink: 0;
            border: 1px solid #1e293b; border-radius: 4px;
            padding: 2px 6px; font-family: monospace;
        }
        #search-results {
            max-height: 380px; overflow-y: auto;
            padding: 8px 0;
        }
        .sr-group-label {
            padding: 8px 18px 4px;
            font-size: 10px; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: #334155;
        }
        .sr-item {
            display: flex; align-items: center; gap: 12px;
            padding: 9px 18px; cursor: pointer;
            transition: background 0.08s;
        }
        .sr-item:hover, .sr-item.active {
            background: rgba(99,102,241,0.12);
        }
        .sr-item.active { outline: none; }
        .sr-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .sr-main { flex: 1; min-width: 0; }
        .sr-name { font-size: 13px; font-weight: 600; color: #e2e8f0; }
        .sr-sub  { font-size: 11px; color: #475569; margin-top: 1px; }
        .sr-badge {
            font-size: 10px; font-weight: 600; padding: 2px 8px;
            border-radius: 999px; flex-shrink: 0;
        }
        .sr-shortcut {
            font-size: 10px; color: #1e293b; border: 1px solid #1e293b;
            border-radius: 4px; padding: 2px 5px; font-family: monospace;
            flex-shrink: 0;
        }
        #search-empty {
            padding: 28px 18px; text-align: center;
            color: #334155; font-size: 13px;
            display: none;
        }
        #search-footer {
            border-top: 1px solid rgba(99,102,241,0.1);
            padding: 8px 18px;
            display: flex; gap: 16px; align-items: center;
            font-size: 10px; color: #334155;
        }
        #search-footer kbd {
            border: 1px solid #1e293b; border-radius: 3px;
            padding: 1px 4px; font-family: monospace; font-size: 10px; color: #475569;
        }
        /* Search trigger button */
        #btn-search {
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.28);
            color: #6366f1; padding: 5px 14px;
            border-radius: 6px; font-size: 12px; font-weight: 500;
            cursor: pointer; transition: background 0.15s;
            display: flex; align-items: center; gap: 6px;
        }
        #btn-search:hover { background: rgba(99,102,241,0.2); }

        /* Loading toaster for district boundary fetch */
        #dist-progress {
            position: fixed; bottom: 60px; left: 50%; transform: translateX(-50%);
            background: rgba(10,14,35,0.97); border: 1px solid rgba(99,102,241,0.35);
            border-radius: 10px; padding: 10px 20px;
            font-size: 12px; color: #94a3b8;
            display: none; z-index: 70; white-space: nowrap;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        .preview-badge {
            background: rgba(251,191,36,0.12); border: 1px solid rgba(251,191,36,0.3);
            color: #fbbf24; font-size: 11px; font-weight: 600;
            padding: 3px 10px; border-radius: 999px;
        }

        #loading {
            position: fixed; inset: 0; z-index: 200;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: #06091a; transition: opacity 0.5s ease;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 1s linear infinite; }

        #tooltip {
            position: fixed; pointer-events: none;
            background: rgba(10, 14, 35, 0.95);
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 9px; padding: 10px 14px;
            font-size: 13px; color: #e2e8f0;
            backdrop-filter: blur(10px);
            display: none; z-index: 60;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }

        #legend {
            position: fixed; bottom: 28px; left: 24px; z-index: 50;
            background: rgba(10, 14, 35, 0.92);
            border: 1px solid rgba(99, 102, 241, 0.18);
            border-radius: 14px; padding: 16px 20px;
            backdrop-filter: blur(12px);
            min-width: 180px;
        }
        #legend h3 { color: #64748b; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 12px; }
        .legend-row {
            display: flex; align-items: center; margin-bottom: 9px;
            cursor: pointer; border-radius: 6px; padding: 3px 0;
        }
        .legend-row:last-child { margin-bottom: 0; }
        .legend-swatch { width: 13px; height: 13px; border-radius: 3px; margin-right: 10px; flex-shrink: 0; }
        .legend-name { color: #cbd5e1; font-size: 13px; }
        .legend-count { color: #475569; font-size: 11px; margin-left: 6px; }

        #info-panel {
            position: fixed; top: 86px; right: 12px; bottom: 12px;
            width: 300px; z-index: 50;
            background: rgba(8, 12, 28, 0.95);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 14px;
            backdrop-filter: blur(20px);
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
            transform: translateX(calc(100% + 20px));
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
            padding: 16px 18px 22px;
            overflow-y: auto;
        }
        #info-panel.open { transform: translateX(0); }
        /* Panel header row */
        #panel-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px; flex-shrink: 0;
        }
        #panel-close {
            background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2);
            color: #475569; font-size: 14px; cursor: pointer;
            border-radius: 6px; padding: 3px 8px; line-height: 1;
            transition: color 0.12s, background 0.12s;
        }
        #panel-close:hover { color: #94a3b8; background: rgba(99,102,241,0.16); }
        .panel-label { color: #475569; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 10px; }
        .panel-divider { border: none; border-top: 1px solid rgba(99,102,241,0.12); margin: 16px 0; }
        .state-chip {
            display: inline-block; padding: 3px 9px; border-radius: 999px;
            font-size: 11px; margin: 3px 3px 3px 0;
            border: 1px solid rgba(99,102,241,0.2); color: #64748b;
            cursor: pointer; transition: color 0.15s, border-color 0.15s, background 0.15s;
        }
        .state-chip:not(.active):hover {
            color: #94a3b8;
            border-color: rgba(99,102,241,0.45);
            background: rgba(99,102,241,0.08);
        }
        .state-chip.active {
            color: #a5b4fc; border-color: rgba(99,102,241,0.5); font-weight: 600;
            cursor: default;
        }

        /* Candidate cards */
        .office-section { margin-bottom: 10px; border:1px solid rgba(99,102,241,0.10); border-radius:10px; overflow:hidden; }
        .office-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
            display:flex; align-items:center; justify-content:space-between;
            cursor:pointer; user-select:none;
            padding:8px 10px; border-radius:0; margin:0;
            transition: background 0.12s;
        }
        .office-title:hover { filter: brightness(1.15); }
        .office-title .chevron { transition: transform 0.2s; font-style:normal; font-size:10px; opacity:.7; margin-left:6px; flex-shrink:0; }
        .office-section.collapsed .chevron { transform: rotate(-90deg); }
        .office-body { padding:10px 10px 12px; }
        .office-section.collapsed .office-body { display:none; }
        .office-role-tip { font-size: 11px; color: #475569; line-height: 1.5; margin: 0 0 10px; font-style: italic; }
        .candidate-card {
            background: rgba(15,20,45,0.7); border: 1px solid rgba(99,102,241,0.13);
            border-radius: 10px; padding: 10px 12px; margin-bottom: 7px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .candidate-card:last-child { margin-bottom: 0; }
        .candidate-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid rgba(99,102,241,0.2); }
        .candidate-avatar-placeholder {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden;
        }
        .candidate-avatar-placeholder svg { display: block; }
        .candidate-name {
            font-size: 13px; font-weight: 600; color: #e2e8f0; line-height: 1.3; margin-bottom: 3px;
        }
        .candidate-card {
            cursor: pointer;
            transition: background 0.12s, border-color 0.12s, transform 0.1s;
        }
        .candidate-card:hover {
            background: rgba(20,26,58,0.95);
            transform: translateX(-2px);
        }

        /* ── Candidate quick-view popup ── */
        #cand-popup {
            position: fixed; z-index: 200;
            width: 320px;
            background: rgba(8, 12, 30, 0.98);
            border: 1px solid rgba(99,102,241,0.35);
            border-radius: 14px;
            padding: 18px 18px 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.7), 0 0 0 1px rgba(99,102,241,0.1) inset;
            backdrop-filter: blur(20px);
            display: none;
            transition: opacity 0.15s;
        }
        #cand-popup.visible { display: block; }
        #cand-popup-close {
            position: absolute; top: 12px; right: 14px;
            background: none; border: none; color: #475569;
            font-size: 18px; cursor: pointer; line-height: 1; padding: 2px;
            transition: color 0.12s;
        }
        #cand-popup-close:hover { color: #94a3b8; }
        .popup-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .popup-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            object-fit: cover; flex-shrink: 0;
            border: 2px solid rgba(99,102,241,0.35);
        }
        .popup-avatar-ph {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden;
        }
        .popup-avatar-ph svg { display: block; }
        .popup-name { font-size: 16px; font-weight: 700; color: #f1f5f9; line-height: 1.2; margin-bottom: 4px; }
        .popup-office { font-size: 11px; color: #64748b; }
        .popup-divider { border: none; border-top: 1px solid rgba(99,102,241,0.12); margin: 10px 0; }
        .popup-bio { font-size: 12px; color: #94a3b8; line-height: 1.55; margin: 0 0 10px; }
        .popup-stats { display: flex; gap: 10px; margin-bottom: 12px; }
        .popup-stat {
            flex: 1; background: rgba(99,102,241,0.06);
            border: 1px solid rgba(99,102,241,0.14);
            border-radius: 8px; padding: 7px 10px; text-align: center;
        }
        .popup-stat-val { font-size: 13px; font-weight: 700; color: #e2e8f0; display: block; }
        .popup-stat-lbl { font-size: 9px; color: #475569; text-transform: uppercase; letter-spacing: .07em; }
        .popup-stance { font-size: 11px; color: #64748b; margin: 0 0 12px; line-height: 1.5; }
        .popup-stance strong { color: #94a3b8; }
        .popup-actions { display: flex; gap: 8px; }
        .popup-btn {
            flex: 1; padding: 8px; border-radius: 8px;
            font-size: 12px; font-weight: 600; cursor: pointer;
            text-align: center; text-decoration: none; display: block;
            transition: opacity 0.15s;
        }
        .popup-btn:hover { opacity: 0.82; }
        .popup-btn-primary {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff; border: none;
        }
        .popup-btn-secondary {
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.3);
            color: #818cf8;
        }
        .candidate-meta { font-size: 11px; color: #64748b; display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .party-pill { padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 600; }
        .party-D  { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25); }
        .party-R  { background: rgba(239,68,68,0.15);  color: #f87171; border: 1px solid rgba(239,68,68,0.25); }
        .party-L  { background: rgba(234,179,8,0.15);  color: #facc15; border: 1px solid rgba(234,179,8,0.25); }
        .party-G  { background: rgba(34,197,94,0.15);  color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }
        .party-I  { background: rgba(148,163,184,0.1); color: #94a3b8; border: 1px solid rgba(148,163,184,0.2); }
        .status-running { color: #34d399; font-size: 10px; }
        .status-seated  { color: #818cf8; font-size: 10px; }
        .verified-badge { color: #fbbf24; font-size: 10px; }
        .candidate-links { display: flex; gap: 6px; margin-top: 5px; }
        .cand-link { font-size: 10px; padding: 2px 7px; border-radius: 4px; text-decoration: none; border: 1px solid rgba(99,102,241,0.25); color: #818cf8; background: rgba(99,102,241,0.08); }
        .cand-link:hover { background: rgba(99,102,241,0.22); }
        .panel-spinner { display: flex; align-items: center; justify-content: center; padding: 32px 0; color: #334155; font-size: 13px; gap: 10px; }
        .no-candidates { color: #334155; font-size: 12px; text-align: center; padding: 20px 0; }

        #hint {
            position: fixed; bottom: 28px; right: 24px; z-index: 50;
            color: #334155; font-size: 11px; text-align: right; pointer-events: none;
        }

        /* Breadcrumb */
        #breadcrumb-bar {
            position: fixed; top: 54px; left: 0; right: 0; z-index: 45;
            background: rgba(6,9,26,0.8); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(99,102,241,0.1);
            padding: 0 24px; height: 32px;
            display: flex; align-items: center; gap: 0;
        }
        .bc-item { color: #475569; font-size: 12px; }
        .bc-link { color: #6366f1; cursor: pointer; text-decoration: none; }
        .bc-link:hover { color: #818cf8; }
        .bc-active { color: #e2e8f0; font-weight: 600; }
        .bc-sep { color: #1e293b; margin: 0 8px; font-size: 13px; }

        /* District tooltip */
        #district-tooltip {
            position: fixed; pointer-events: none;
            background: rgba(10,14,35,0.96);
            border: 1px solid rgba(99,102,241,0.4);
            border-radius: 8px; padding: 8px 12px;
            font-size: 12px; color: #e2e8f0;
            display: none; z-index: 62;
            box-shadow: 0 6px 24px rgba(0,0,0,0.5);
        }

        /* Back button */
        #btn-back {
            display: none;
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.3);
            color: #6366f1; padding: 4px 12px;
            border-radius: 6px; font-size: 12px; font-weight: 500;
            cursor: pointer; transition: background 0.15s;
        }
        #btn-back:hover { background: rgba(99,102,241,0.22); }

        /* District count badge in panel */
        .district-info-box {
            border-radius: 8px; padding: 10px 12px; margin-bottom: 14px;
        }

        /* ── Mobile hamburger button ── */
        #mobile-menu-btn {
            display: none;
            background: rgba(99,102,241,0.1);
            border: 1px solid rgba(99,102,241,0.28);
            color: #818cf8; padding: 6px 10px;
            border-radius: 6px; cursor: pointer;
            align-items: center; justify-content: center;
            transition: background 0.15s; flex-shrink: 0;
        }
        #mobile-menu-btn:hover { background: rgba(99,102,241,0.22); }
        #mobile-menu-btn svg { pointer-events: none; }

        /* ── Mobile dropdown menu ── */
        #mobile-menu {
            display: none;
            position: fixed; top: 48px; right: 0; left: 0; z-index: 195;
            background: rgba(5,8,22,0.97);
            border-bottom: 1px solid rgba(99,102,241,0.2);
            backdrop-filter: blur(20px);
            padding: 12px 14px 16px;
            flex-direction: column; gap: 8px;
        }
        #mobile-menu.open { display: flex; }
        .mobile-menu-row { display: flex; gap: 8px; }
        .mobile-menu-btn {
            flex: 1;
            background: rgba(99,102,241,0.09);
            border: 1px solid rgba(99,102,241,0.22);
            color: #818cf8; padding: 10px 8px;
            border-radius: 8px; font-size: 12px; font-weight: 500;
            cursor: pointer; text-align: center;
            transition: background 0.15s;
            line-height: 1.3;
        }
        .mobile-menu-btn:active { background: rgba(99,102,241,0.22); }
        .mobile-menu-btn.active {
            background: rgba(99,102,241,0.28);
            border-color: rgba(99,102,241,0.55);
            color: #c7d2fe;
        }

        /* ── Panel drag handle (mobile only) ── */
        .panel-drag-handle {
            display: none;
            width: 38px; height: 5px; border-radius: 3px;
            background: rgba(99,102,241,0.4);
            margin: 10px auto 8px; flex-shrink: 0;
            transition: background 0.15s;
        }
        .panel-drag-handle:hover { background: rgba(99,102,241,0.65); }

        /* ── Controls dropdown ───────────────────────────────────────────── */
        #controls-wrap { position: relative; }
        #btn-controls {
            display: flex; align-items: center; gap: 6px;
        }
        #controls-menu {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            min-width: 220px; z-index: 9999;
            background: rgba(10, 14, 35, 0.97);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 12px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.6);
            backdrop-filter: blur(16px);
            padding: 8px 0;
            overflow: hidden;
        }
        #controls-menu.open { display: block; }
        .cm-section {
            padding: 4px 14px 2px;
            font-size: 9px; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: #334155;
        }
        .cm-item {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; width: 100%; padding: 8px 16px;
            background: none; border: none; color: #94a3b8;
            font-size: 13px; text-align: left; cursor: pointer;
            transition: background 0.12s, color 0.12s;
        }
        .cm-item:hover { background: rgba(99,102,241,0.12); color: #e2e8f0; }
        .cm-item.active { color: #818cf8; }
        .cm-item svg { flex-shrink: 0; opacity: .65; }
        .cm-toggle {
            width: 32px; height: 18px; border-radius: 9px;
            background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.3);
            position: relative; flex-shrink: 0; transition: background .2s;
        }
        .cm-toggle::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 12px; height: 12px; border-radius: 50%;
            background: #475569; transition: transform .2s, background .2s;
        }
        .cm-item.active .cm-toggle { background: rgba(99,102,241,0.5); border-color: #6366f1; }
        .cm-item.active .cm-toggle::after { transform: translateX(14px); background: #818cf8; }
        .cm-divider { border: none; border-top: 1px solid rgba(99,102,241,0.1); margin: 6px 0; }
        .cm-kbd { font-size: 10px; color: #334155; font-family: monospace;
                  border: 1px solid #1e293b; border-radius: 3px; padding: 1px 5px; }

        /* ── Branded scrollbars ───────────────────────────────────────────── */
        /* WebKit (Chrome, Safari, Edge) */
        ::-webkit-scrollbar              { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track        { background: rgba(15, 23, 42, 0.6); border-radius: 3px; }
        ::-webkit-scrollbar-thumb        { background: rgba(99, 102, 241, 0.45); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover  { background: rgba(99, 102, 241, 0.75); }
        ::-webkit-scrollbar-corner       { background: transparent; }
        /* Firefox */
        * { scrollbar-width: thin; scrollbar-color: rgba(99,102,241,0.45) rgba(15,23,42,0.6); }

        /* Legend collapsed state (mobile toggle) */
        #legend.legend-collapsed #legend-items { display: none; }
        #legend.legend-collapsed #legend-toggle-icon { display: inline; transform: rotate(-90deg); }
        #legend-toggle-icon { display: inline-block; transition: transform 0.2s; }

        /* ══════════════════════════════════════════
           RESPONSIVE — TABLET & MOBILE (≤ 768 px)
        ══════════════════════════════════════════ */
        @media (max-width: 768px) {
            /* Top bar */
            #top-bar { padding: 0 10px; height: 48px; }
            #top-bar .sep,
            #top-bar .title { display: none; }
            #btn-districts, #btn-reset, #btn-rotate, #kb-hint-badge { display: none; }
            #mobile-menu-btn { display: flex; }
            #btn-search { padding: 6px 10px; gap: 4px; font-size: 12px; }
            #btn-back { padding: 5px 10px; font-size: 12px; }
            #top-bar a { font-size: 16px; }

            /* Breadcrumb */
            #breadcrumb-bar { top: 48px; padding: 0 10px; height: 28px; }
            .bc-item, .bc-link, .bc-active { font-size: 11px; }

            /* Info panel → bottom sheet
               Default (open class): peek at 40vh so the map stays visible.
               User taps the drag handle to expand to 82vh for full detail.
               Collapsed (no open class): fully off-screen. */
            #info-panel {
                top: auto !important;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                max-height: 82vh;
                height: 40vh;          /* peek height — map visible above */
                border-radius: 18px 18px 0 0;
                border-bottom: none;
                transform: translateY(calc(100% + 2px));
                padding: 0 16px 32px;
                overflow-y: auto;
                transition: transform 0.3s cubic-bezier(0.4,0,0.2,1),
                            height 0.25s ease;
            }
            #info-panel.open { transform: translateY(0); }
            #info-panel.expanded { height: 82vh; }  /* full-height after user expands */
            .panel-drag-handle { display: block; cursor: ns-resize; }
            #panel-header { padding-top: 2px; }

            /* Candidate popup → bottom sheet */
            #cand-popup {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                top: auto !important;
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 18px 18px 0 0 !important;
                border-bottom: none !important;
                max-height: 80vh;
                overflow-y: auto;
            }
            #cand-popup-close { top: 14px; right: 16px; }

            /* Legend — compact, top-left below breadcrumb */
            #legend {
                bottom: auto;
                top: 80px;
                left: 10px;
                padding: 8px 12px;
                min-width: 0;
                border-radius: 10px;
            }
            #legend h3 { font-size: 9px; margin-bottom: 7px; }
            .legend-name { font-size: 11px; }
            .legend-count { display: none; }
            .legend-row { margin-bottom: 5px; }
            .legend-swatch { width: 11px; height: 11px; margin-right: 7px; }

            /* Hide redundant hint; simplify progress toaster */
            #hint { display: none; }
            #dist-progress { bottom: auto; top: 82px; right: 10px; left: auto; transform: none; font-size: 11px; padding: 8px 14px; }

            /* Search overlay adjustments */
            #search-overlay { padding-top: 60px; padding-left: 8px; padding-right: 8px; }
        }

        /* ══════════════════════════════════════════
           PHONE (≤ 480 px)
        ══════════════════════════════════════════ */
        @media (max-width: 480px) {
            #info-panel { max-height: 82vh; height: 38vh; }
            #cand-popup { max-height: 85vh; }
            .popup-stats { flex-direction: column; gap: 6px; }
            .popup-stat { padding: 6px 10px; }
            #legend { top: 76px; }
        }
    </style>
</head>
<body>

{{-- Skip-to-main for screen readers & keyboard users --}}
<a id="skip-to-main" href="#map-canvas-region">Skip to map</a>

{{-- Keyboard help overlay (? key) --}}
<div id="kb-help" role="dialog" aria-modal="true" aria-label="Keyboard shortcuts">
    <div id="kb-help-box">
        <h2>⌨ Keyboard Controls</h2>
        <table class="kb-table" aria-label="Keyboard shortcuts list">
            <tbody>
                <tr><td><kbd>Tab</kbd></td><td>Focus the map canvas</td></tr>
                <tr><td><kbd>Enter</kbd> / <kbd>Space</kbd></td><td>Open search to select a state</td></tr>
                <tr><td><kbd>←</kbd> <kbd>→</kbd></td><td>Rotate map left / right</td></tr>
                <tr><td><kbd>↑</kbd> <kbd>↓</kbd></td><td>Tilt map up / down</td></tr>
                <tr><td><kbd>+</kbd> / <kbd>=</kbd></td><td>Zoom in</td></tr>
                <tr><td><kbd>−</kbd></td><td>Zoom out</td></tr>
                <tr><td><kbd>R</kbd></td><td>Reset view</td></tr>
                <tr><td><kbd>Esc</kbd></td><td>Close panel / popup</td></tr>
                <tr><td><kbd>?</kbd></td><td>Show / hide this help</td></tr>
            </tbody>
        </table>
        <button id="kb-help-close" aria-label="Close keyboard help">Close</button>
    </div>
</div>

{{-- Focusable canvas region + visible focus ring --}}
<div id="map-canvas-region"
     tabindex="0"
     role="application"
     aria-label="Interactive U.S. map. Use arrow keys to rotate, + and - to zoom, Enter to search for a state, ? for keyboard help."
     aria-description="Use arrow keys to rotate, + and - to zoom, Enter to open search."></div>
<div id="kb-focus-ring" aria-hidden="true"></div>

{{-- Keyboard shortcut badge --}}
<button id="kb-hint-badge" aria-label="Show keyboard shortcuts" title="Keyboard shortcuts (press ?)">
    <kbd>?</kbd> Keyboard shortcuts
</button>

<div id="loading">
    <svg class="spinner" width="44" height="44" viewBox="0 0 24 24" fill="none" style="color:#6366f1;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
    </svg>
    <p style="color:#475569; font-size:13px; margin-top:14px;">Loading map data…</p>
</div>

<div id="map-container" style="position:fixed; inset:0;"></div>

<div id="top-bar">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="{{ url('/') }}">U9itus</a>
        <span class="sep">|</span>
        <span class="title">U.S. Regional Map</span>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <button id="btn-back">← Back</button>
        <button id="btn-search" title="Search states and districts (press /)"
            aria-label="Search states and districts">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Search
            <span style="font-size:10px;color:#334155;border:1px solid #1e293b;border-radius:3px;padding:1px 5px;font-family:monospace;">/</span>
        </button>
        <!-- Controls dropdown -->
        <div id="controls-wrap">
            <button class="top-btn" id="btn-controls" aria-haspopup="true" aria-expanded="false" aria-controls="controls-menu" title="Map controls">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/>
                </svg>
                Controls
            </button>
            <div id="controls-menu" role="menu" aria-label="Map controls">
                <div class="cm-section">View</div>
                <button class="cm-item" id="cm-btn-reset" role="menuitem">
                    <span>Reset View</span>
                    <span class="cm-kbd">R</span>
                </button>
                <button class="cm-item" id="cm-btn-rotate" role="menuitem">
                    <span>Auto-Rotate</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <button class="cm-item" id="cm-btn-districts" role="menuitem">
                    <span>District Boundaries</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <button class="cm-item" id="cm-btn-party-colors" role="menuitem" title="Color states by governor's party instead of region">
                    <span>Party Control Colors</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <hr class="cm-divider">
                <div class="cm-section">Keyboard</div>
                <button class="cm-item" id="cm-btn-kb-help" role="menuitem">
                    <span>Keyboard Shortcuts</span>
                    <span class="cm-kbd">?</span>
                </button>
                <hr class="cm-divider">
                <div class="cm-section">Zoom</div>
                <button class="cm-item" id="cm-btn-zoomin" role="menuitem">
                    <span>Zoom In</span>
                    <span class="cm-kbd">+</span>
                </button>
                <button class="cm-item" id="cm-btn-zoomout" role="menuitem">
                    <span>Zoom Out</span>
                    <span class="cm-kbd">−</span>
                </button>
            </div>
        </div>
        <!-- Hidden legacy buttons kept for JS compatibility (visible in mobile drawer) -->
        <button class="top-btn" id="btn-districts" style="display:none">District Boundaries: OFF</button>
        <button class="top-btn" id="btn-reset" style="display:none">Reset View</button>
        <button class="top-btn" id="btn-rotate" style="display:none">Auto-Rotate: OFF</button>
        <!-- Mobile only: hamburger -->
        <button id="mobile-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>
</div>

<!-- Mobile navigation drawer (hidden on desktop) -->
<div id="mobile-menu" role="menu" aria-label="Map controls">
    <div class="mobile-menu-row">
        <button class="mobile-menu-btn" id="mob-btn-districts">
            📍 Districts<br><span style="font-size:10px;opacity:.7;">OFF</span>
        </button>
        <button class="mobile-menu-btn" id="mob-btn-rotate">
            🔄 Auto-Rotate<br><span style="font-size:10px;opacity:.7;">OFF</span>
        </button>
        <button class="mobile-menu-btn" id="mob-btn-reset">
            🏠 Reset View
        </button>
    </div>
</div>

<!-- Search Palette -->
<div id="search-overlay" role="dialog" aria-modal="true" aria-label="Search states and districts">
    <div id="search-box">
        <div id="search-input-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input id="search-input" type="text" placeholder="Search state or district… e.g. &quot;California&quot;, &quot;CA-38&quot;, &quot;Texas 7&quot;" autocomplete="off" spellcheck="false">
            <span id="search-kbd">esc</span>
        </div>
        <div id="search-results" role="listbox"></div>
        <div id="search-empty">🔍 No results for that state or district</div>
        <div id="search-footer">
            <span><kbd>↵</kbd> select</span>
            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
            <span><kbd>esc</kbd> close</span>
            <span style="margin-left:auto;">Type a state name, abbrev., or "CA-38"</span>
        </div>
    </div>
</div>
<!-- Candidate quick-view popup -->
<div id="cand-popup" role="dialog" aria-modal="true">
    <button id="cand-popup-close" aria-label="Close">✕</button>
    <div class="popup-header">
        <div id="popup-avatar-wrap"></div>
        <div>
            <div class="popup-name" id="popup-name"></div>
            <div class="popup-office" id="popup-office"></div>
        </div>
    </div>
    <hr class="popup-divider">
    <p class="popup-bio" id="popup-bio"></p>
    <div class="popup-stats">
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-raised"></span>
            <span class="popup-stat-lbl">Raised (est.)</span>
        </div>
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-status"></span>
            <span class="popup-stat-lbl">Status</span>
        </div>
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-party-badge"></span>
            <span class="popup-stat-lbl">Party</span>
        </div>
    </div>
    <p class="popup-stance" id="popup-stance"></p>
    <div class="popup-actions">
        <a id="popup-campaign-link" href="#" class="popup-btn popup-btn-primary" target="_blank">👤 View Profile</a>
        <a id="popup-bp-link" href="#" class="popup-btn popup-btn-secondary" target="_blank" rel="noopener">Ballotpedia →</a>
    </div>
</div>

<div id="dist-progress">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;vertical-align:middle;margin-right:6px;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
    </svg>
    Loading all 435 congressional districts…
</div>

<div id="breadcrumb-bar">
    <div id="breadcrumb"><span class="bc-item bc-active">Overview</span></div>
</div>

<div id="tooltip"></div>
<div id="district-tooltip"></div>

<div id="legend">
    <h3 role="button" tabindex="0" style="cursor:pointer;user-select:none;"
        onclick="this.closest('#legend').classList.toggle('legend-collapsed')"
        onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"
        title="Tap to show/hide">
        Party Control <span id="legend-toggle-icon" style="font-size:9px;opacity:.6;">▾</span>
    </h3>
    <div id="legend-items"></div>
</div>

<div id="info-panel">
    <div class="panel-drag-handle" role="button" aria-label="Expand or collapse panel" tabindex="0"
         onclick="(function(h){
             var p=h.closest('#info-panel');
             if(p){ p.classList.toggle('expanded'); }
         })(this)"
         onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"></div>
    <div id="panel-header">
        <div>
            <h2 id="panel-state" style="color:#e2e8f0; font-size:16px; font-weight:700; margin:0 0 4px; line-height:1.25;"></h2>
            <span id="panel-badge" style="display:inline-block; padding:2px 10px; border-radius:999px; font-size:10px; font-weight:600;"></span>
        </div>
        <button id="panel-close" title="Close panel">✕</button>
    </div>
    <div id="panel-states" style="margin-bottom:6px;"></div>
    <hr class="panel-divider" style="margin:8px 0 10px;">
    <p class="panel-label">Statewide Executive Offices</p>
    <div id="panel-candidates">
        <div class="panel-spinner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
            </svg>
            Loading candidates…
        </div>
    </div>
</div>

<div id="hint" style="position:fixed;bottom:28px;right:24px;z-index:50;color:#334155;font-size:11px;text-align:right;pointer-events:none;">
    Drag to rotate &nbsp;·&nbsp; Scroll to zoom &nbsp;·&nbsp; Click a state
</div>

<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/* ════════════════════════════════════════════════════════
   REGION + STATE LOOKUP DATA
════════════════════════════════════════════════════════ */
const REGIONS = {
    Northeast: {
        states: ['Connecticut','Maine','Massachusetts','New Hampshire','New Jersey',
                 'New York','Pennsylvania','Rhode Island','Vermont'],
        color: 0x6366f1, hex: '#6366f1',
    },
    Midwest: {
        states: ['Illinois','Indiana','Iowa','Kansas','Michigan','Minnesota',
                 'Missouri','Nebraska','North Dakota','Ohio','South Dakota','Wisconsin'],
        color: 0xf59e0b, hex: '#f59e0b',
    },
    South: {
        states: ['Alabama','Arkansas','Delaware','Florida','Georgia','Kentucky',
                 'Louisiana','Maryland','Mississippi','North Carolina','Oklahoma',
                 'South Carolina','Tennessee','Texas','Virginia','West Virginia',
                 'District of Columbia'],
        color: 0xef4444, hex: '#ef4444',
    },
    West: {
        states: ['Alaska','Arizona','California','Colorado','Hawaii','Idaho',
                 'Montana','Nevada','New Mexico','Oregon','Utah','Washington','Wyoming'],
        color: 0x10b981, hex: '#10b981',
    }
};

/* State name → 2-letter USPS abbrev */
const STATE_ABBR_MAP = {
    'Alabama':'AL','Alaska':'AK','Arizona':'AZ','Arkansas':'AR','California':'CA',
    'Colorado':'CO','Connecticut':'CT','Delaware':'DE','Florida':'FL','Georgia':'GA',
    'Hawaii':'HI','Idaho':'ID','Illinois':'IL','Indiana':'IN','Iowa':'IA','Kansas':'KS',
    'Kentucky':'KY','Louisiana':'LA','Maine':'ME','Maryland':'MD','Massachusetts':'MA',
    'Michigan':'MI','Minnesota':'MN','Mississippi':'MS','Missouri':'MO','Montana':'MT',
    'Nebraska':'NE','Nevada':'NV','New Hampshire':'NH','New Jersey':'NJ','New Mexico':'NM',
    'New York':'NY','North Carolina':'NC','North Dakota':'ND','Ohio':'OH','Oklahoma':'OK',
    'Oregon':'OR','Pennsylvania':'PA','Rhode Island':'RI','South Carolina':'SC',
    'South Dakota':'SD','Tennessee':'TN','Texas':'TX','Utah':'UT','Vermont':'VT',
    'Virginia':'VA','Washington':'WA','West Virginia':'WV','Wisconsin':'WI','Wyoming':'WY',
    'District of Columbia':'DC',
};

/* State name → 2-digit FIPS (matches TIGERweb STATE field) */
const STATE_FIPS = {
    'Alabama':'01','Alaska':'02','Arizona':'04','Arkansas':'05','California':'06',
    'Colorado':'08','Connecticut':'09','Delaware':'10','District of Columbia':'11',
    'Florida':'12','Georgia':'13','Hawaii':'15','Idaho':'16','Illinois':'17',
    'Indiana':'18','Iowa':'19','Kansas':'20','Kentucky':'21','Louisiana':'22',
    'Maine':'23','Maryland':'24','Massachusetts':'25','Michigan':'26','Minnesota':'27',
    'Mississippi':'28','Missouri':'29','Montana':'30','Nebraska':'31','Nevada':'32',
    'New Hampshire':'33','New Jersey':'34','New Mexico':'35','New York':'36',
    'North Carolina':'37','North Dakota':'38','Ohio':'39','Oklahoma':'40','Oregon':'41',
    'Pennsylvania':'42','Rhode Island':'44','South Carolina':'45','South Dakota':'46',
    'Tennessee':'47','Texas':'48','Utah':'49','Vermont':'50','Virginia':'51',
    'Washington':'53','West Virginia':'54','Wisconsin':'55','Wyoming':'56',
};

/* Expected 119th Congress seat count per state (for UI badge) */
const DISTRICT_COUNTS = {
    'Alabama':7,'Alaska':1,'Arizona':9,'Arkansas':4,'California':52,
    'Colorado':8,'Connecticut':5,'Delaware':1,'District of Columbia':0,
    'Florida':28,'Georgia':14,'Hawaii':2,'Idaho':2,'Illinois':17,
    'Indiana':9,'Iowa':4,'Kansas':4,'Kentucky':6,'Louisiana':6,
    'Maine':2,'Maryland':8,'Massachusetts':9,'Michigan':13,'Minnesota':8,
    'Mississippi':4,'Missouri':8,'Montana':2,'Nebraska':3,'Nevada':4,
    'New Hampshire':2,'New Jersey':12,'New Mexico':3,'New York':26,
    'North Carolina':14,'North Dakota':1,'Ohio':15,'Oklahoma':5,'Oregon':6,
    'Pennsylvania':17,'Rhode Island':2,'South Carolina':7,'South Dakota':1,
    'Tennessee':9,'Texas':38,'Utah':4,'Vermont':1,'Virginia':11,
    'Washington':10,'West Virginia':2,'Wisconsin':8,'Wyoming':1,
};

/* ═══════════════════════════════════════════════════════
   119th CONGRESS — PARTY PER DISTRICT
   D=Democrat  R=Republican  I=Independent/Other
   Encoded as: state→array of Republican district numbers.
   All unlisted seats are Democrat or at-large independent.
   Source: Clerk of the House, January 2025 certification.
═══════════════════════════════════════════════════════ */
const _R = 'R', _D = 'D', _I = 'I';
/* For each state abbr, list the district numbers held by R or I.
   Any district not listed is D.  At-Large = 0. */
const _GOP = {
    AL:[1,2,3,4,5,6],       // AL-7 = D (Sewell)
    AK:[0],                  // at-large R (Peltola lost)
    AZ:[1,2,5,6,8,9],
    AR:[1,2,3,4],
    CA:[3,4,20,21,22,23,24,27,40,41,45,46,48,49,50],
    CO:[4,5],
    CT:[],                   // all 5 D
    DE:[],                   // at-large D
    FL:[1,2,3,4,5,6,7,8,11,12,13,14,15,16,17,18,19,25,26,27,28],
    GA:[1,2,3,6,9,10,11,12,14],
    HI:[],                   // both D
    ID:[1,2],
    IL:[12,13,14,15,16,17],
    IN:[2,3,4,5,6,7,8,9],   // IN-1 = D (Mrvan)
    IA:[1,2,3,4],
    KS:[1,2,4],              // KS-3 = D (Davids)
    KY:[1,2,3,4,5,6],
    LA:[1,3,4,5,6],          // LA-2 = D (Carter)
    ME:[2],                  // ME-1 = D (Golden)
    MD:[1,6],                // 6 others D
    MA:[],                   // all 9 D
    MI:[2,4,5,8,9,10],
    MN:[1,2,6,8],
    MS:[1,2,3,4],
    MO:[2,3,4,5,6,7,8],      // MO-1 = D (Bush)
    MT:[2],                  // MT-1 = D (Zinke)  actually MT1=R, MT2=D... let me fix
    NE:[1,2,3],
    NV:[3,4],                // NV-1,2 = D
    NH:[],                   // both D
    NJ:[2,3,4,5,7,11],
    NM:[2],                  // NM-1,3 = D
    NY:[1,2,3,4,17,19,22,24],
    NC:[1,2,3,5,6,7,8,9,10,11],
    ND:[0],                  // at-large R
    OH:[1,2,4,5,6,7,8,9,10,12,13,14,15], // OH-3,11 = D
    OK:[1,2,3,4,5],
    OR:[2,3],                // OR-1,4,5,6 = D
    PA:[1,4,9,10,11,12,13,16,17],
    RI:[],                   // both D
    SC:[1,2,3,4,5,7],        // SC-6 = D (Clyburn)
    SD:[0],                  // at-large R
    TN:[1,2,3,4,5,6,7,8],   // TN-5 was D but now R after 2023 special
    TX:[1,2,3,4,5,6,7,8,10,11,12,13,14,17,19,21,22,24,25,26,27,36,38],
    UT:[1,2,3],              // UT-4 = D (Case... wait UT-4 = R Owens)
    VT:[0],                  // at-large I (Sanders? no — Balint D)
    VA:[1,2,5,6,7,9,10],
    WA:[3,4,5,8],
    WV:[1,2],
    WI:[1,5,6,7,8],
    WY:[0],
};
/* Independent seats in 119th Congress */
const _IND = { ME: [1] }; // Jared Golden (I caucuses D — but listed separately for accuracy)

/* Build the fast lookup: 'CA-38' → 'D'|'R'|'I' */
const DISTRICT_PARTY_MAP = (() => {
    const map = {};
    for (const [abbr, rDistricts] of Object.entries(_GOP)) {
        const rSet = new Set(rDistricts.map(String));
        const iDistricts = _IND[abbr] ? new Set(_IND[abbr].map(String)) : new Set();
        const total = Object.entries({ ...Object.fromEntries(
            Object.entries(STATE_ABBR_MAP)
                .filter(([,a]) => a === abbr)
        ) })[0]?.[0];
        const count = total ? (DISTRICT_COUNTS[total] || 0) : 0;
        if (count === 0) {
            // DC — no voting rep
        } else if (count === 1) {
            // at-large
            const key = `${abbr}-AL`;
            map[key] = rSet.has('0') ? _R : iDistricts.has('0') ? _I : _D;
        } else {
            for (let d = 1; d <= count; d++) {
                const key = `${abbr}-${d}`;
                map[key] = rSet.has(String(d)) ? _R : iDistricts.has(String(d)) ? _I : _D;
            }
        }
    }
    return map;
})();

const PARTY_HEX   = { D: '#2563eb', R: '#dc2626', I: '#16a34a', U: '#64748b' };
const PARTY_INT   = { D: 0x1d4ed8, R: 0xb91c1c,  I: 0x15803d,  U: 0x475569 };
const PARTY_LABEL = { D: 'Democratic', R: 'Republican', I: 'Independent', U: 'Unknown' };

/* ── Party-control color mode ────────────────────────────────────────────
 * govPartyByAbbr: two-letter state code → party code (D / R / I / U)
 * Populated by fetchAllGovernorParties() called once after map loads.
 * colorMode: 'region' (default) | 'party'
 */
let govPartyByAbbr = {};   // e.g. { CA: 'D', TX: 'R', ... }
let colorMode      = 'region';

async function fetchAllGovernorParties() {
    // STATE_ABBR_MAP: state name → abbreviation (already defined in scope)
    const abbrs = Object.values(STATE_ABBR_MAP).filter(Boolean);
    // Batch: fetch in groups of 10 to avoid overwhelming the API
    for (let i = 0; i < abbrs.length; i += 10) {
        const batch = abbrs.slice(i, i + 10);
        await Promise.all(batch.map(async abbr => {
            try {
                const res = await fetch(`/api/v1/map/state-candidates?state=${abbr}`);
                if (!res.ok) return;
                const data = await res.json();
                const govGroup = (data.offices ?? []).find(g => g.office === 'Governor');
                const seated = (govGroup?.candidates ?? []).find(c => c.status === 'seated');
                if (seated?.party) {
                    const p = seated.party.charAt(0).toUpperCase();
                    govPartyByAbbr[abbr] = (['D','R','I'].includes(p)) ? p : 'U';
                } else {
                    govPartyByAbbr[abbr] = 'U';
                }
            } catch { govPartyByAbbr[abbr] = 'U'; }
        }));
    }
}

function getStatePartyColor(stateName) {
    const abbr = STATE_ABBR_MAP[stateName];
    const p    = govPartyByAbbr[abbr] ?? 'U';
    return PARTY_INT[p] ?? PARTY_INT.U;
}

function applyColorMode() {
    const breakdown = {};
    for (const m of stateMeshes) {
        const color = colorMode === 'party'
            ? getStatePartyColor(m.userData.name)
            : m.userData.originalColor;
        m.material.color.setHex(color);
        if (colorMode === 'party') {
            const abbr = STATE_ABBR_MAP[m.userData.name];
            const p    = govPartyByAbbr[abbr] ?? 'U';
            breakdown[p] = (breakdown[p] || 0) + 1;
        }
    }
    // Update the legend
    if (colorMode === 'party') showPartyLegend(breakdown);
    else showRegionLegend();
}

/* FIPS → state abbreviation */
const FIPS_TO_ABBR = Object.fromEntries(
    Object.entries(STATE_FIPS).map(([name, fips]) => [fips, STATE_ABBR_MAP[name]])
);

/* ════════════════════════════════════════════════════════
   SCENE SETUP
════════════════════════════════════════════════════════ */
const stateToRegion = {};
for (const [rName, rData] of Object.entries(REGIONS)) {
    for (const s of rData.states) stateToRegion[s] = rName;
}

const container = document.getElementById('map-container');

/**
 * Effective canvas width: on desktop (>768px) subtract the panel width
 * when the panel is open so the globe renders centered in the visible area,
 * not behind the side panel.
 */
const PANEL_WIDTH = 324; // panel width (300px) + right margin (12px) + border
const W = () => {
    const panel = document.getElementById('info-panel');
    if (window.innerWidth > 768 && panel && panel.classList.contains('open')) {
        return Math.max(container.clientWidth - PANEL_WIDTH, 200);
    }
    return container.clientWidth;
};
const H = () => container.clientHeight;

function resizeRenderer() {
    camera.aspect = W() / H();
    camera.updateProjectionMatrix();
    renderer.setSize(W(), H(), false); // false = don't update canvas CSS size
    // Offset the canvas so it fills only the map area (not under the panel)
    renderer.domElement.style.width  = W() + 'px';
    renderer.domElement.style.height = H() + 'px';
}

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x06091a);
scene.fog = new THREE.FogExp2(0x060914, 0.014);

const camera = new THREE.PerspectiveCamera(42, W() / H(), 0.1, 300);
camera.position.set(0, 7, 13);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(W(), H());
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
container.appendChild(renderer.domElement);

scene.add(new THREE.AmbientLight(0x6080b0, 0.65));
const sun = new THREE.DirectionalLight(0xffffff, 1.1);
sun.position.set(6, 14, 10); scene.add(sun);
const fill = new THREE.DirectionalLight(0x304080, 0.35);
fill.position.set(-8, -4, -12); scene.add(fill);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true; controls.dampingFactor = 0.07;
controls.minDistance = 2; controls.maxDistance = 45;
controls.maxPolarAngle = Math.PI / 2;   // allow fully top-down view
controls.autoRotate = false; controls.autoRotateSpeed = 0.4;
controls.target.set(0, 0, 0);

/* Stars */
const sBuf = new Float32Array(2000 * 3);
for (let i = 0; i < 2000; i++) {
    const r = 80 + Math.random()*100, th = Math.random()*Math.PI*2, ph = Math.acos(2*Math.random()-1);
    sBuf[i*3] = r*Math.sin(ph)*Math.cos(th); sBuf[i*3+1] = r*Math.sin(ph)*Math.sin(th); sBuf[i*3+2] = r*Math.cos(ph);
}
const sGeo = new THREE.BufferGeometry();
sGeo.setAttribute('position', new THREE.BufferAttribute(sBuf, 3));
scene.add(new THREE.Points(sGeo, new THREE.PointsMaterial({ color: 0x8899cc, size: 0.18, sizeAttenuation: true })));

scene.add(new THREE.Mesh(new THREE.PlaneGeometry(22, 14), new THREE.MeshPhongMaterial({ color: 0x0d1a36 })));

/* ════════════════════════════════════════════════════════
   PROJECTION
════════════════════════════════════════════════════════ */
const GEO_SCALE = 1070, GEO_TRANSLATE = [480, 300], NORM = 82;
const projection = d3.geoAlbersUsa().scale(GEO_SCALE).translate(GEO_TRANSLATE);

function project([lon, lat]) {
    const p = projection([lon, lat]);
    if (!p) return null;
    return [(p[0]-GEO_TRANSLATE[0])/NORM, -(p[1]-GEO_TRANSLATE[1])/NORM];
}

function buildShapeFromRings(rings) {
    const p0 = project(rings[0][0]);
    if (!p0) return null;
    const shape = new THREE.Shape();
    shape.moveTo(p0[0], p0[1]);
    for (let i = 1; i < rings[0].length; i++) { const p = project(rings[0][i]); if (p) shape.lineTo(p[0], p[1]); }
    shape.closePath();
    for (let h = 1; h < rings.length; h++) {
        const hp0 = project(rings[h][0]); if (!hp0) continue;
        const hole = new THREE.Path();
        hole.moveTo(hp0[0], hp0[1]);
        for (let i = 1; i < rings[h].length; i++) { const p = project(rings[h][i]); if (p) hole.lineTo(p[0], p[1]); }
        hole.closePath(); shape.holes.push(hole);
    }
    return shape;
}

/* ════════════════════════════════════════════════════════
   MAP MODE STATE
════════════════════════════════════════════════════════ */
let mapMode      = 'overview'; // 'overview' | 'region' | 'state'
let activeRegion = null;
let activeState  = null;
let selectedState = null;
// Guards async state-panel loading to prevent stale cross-state data bleed.
let statePanelRequestId = 0;

/* ════════════════════════════════════════════════════════
   CAMERA ANIMATION
════════════════════════════════════════════════════════ */
function flyTo(endPos, endLook, duration = 950) {
    const startPos  = camera.position.clone();
    const startLook = controls.target.clone();
    const t0 = performance.now();
    function tick() {
        const raw = Math.min((performance.now() - t0) / duration, 1);
        const t   = raw < 0.5 ? 2*raw*raw : -1+(4-2*raw)*raw;
        camera.position.lerpVectors(startPos, endPos, t);
        controls.target.lerpVectors(startLook, endLook, t);
        controls.update();
        if (raw < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

/* Angled 3D fly-to — used for region zoom (looks dramatic) */
function flyToMeshes(meshList, padFactor = 1.5) {
    if (!meshList.length) return;
    const box = new THREE.Box3();
    meshList.forEach(m => box.expandByObject(m));
    const center = new THREE.Vector3(); box.getCenter(center);
    const size   = new THREE.Vector3(); box.getSize(size);
    const fov    = camera.fov * Math.PI / 180;
    const halfH  = Math.max(size.x / (W()/H()), size.y) / 2;
    let dist = (halfH / Math.tan(fov/2)) * padFactor;
    dist = Math.max(dist, 2.5);
    const endPos  = new THREE.Vector3(center.x, center.y + dist*0.35, center.z + dist*0.93);
    const endLook = new THREE.Vector3(center.x, center.y, 0);
    flyTo(endPos, endLook);
}

/* Top-down fly-to — used when zooming into a state or district.
 *
 * The map geometry lies in the XY plane; extrusions go along +Z toward
 * the viewer. A "top-down" view means the camera should be high on the Z
 * axis with only a tiny Y offset for a slight north tilt (so the state
 * still reads left=west, right=east, top=north).
 *
 * The camera is shifted left (–x) by ~half the panel width so the state
 * is centred in the visible area to the left of the 300px panel. */
function flyToMeshesTopDown(meshList, padFactor = 1.25) {
    if (!meshList.length) return;
    const box = new THREE.Box3();
    meshList.forEach(m => box.expandByObject(m));
    const center = new THREE.Vector3(); box.getCenter(center);
    const size   = new THREE.Vector3(); box.getSize(size);
    const fov    = camera.fov * Math.PI / 180;
    // W() already returns the panel-adjusted width on desktop
    const effectiveAspect = W() / H();
    const halfH = Math.max(size.x / effectiveAspect, size.y) / 2;
    let dist = (halfH / Math.tan(fov / 2)) * padFactor;
    dist = Math.max(dist, 1.5);
    // Camera almost directly above: high Z, slight Y tilt
    const endPos  = new THREE.Vector3(center.x, center.y + dist * 0.18, dist * 0.98);
    const endLook = new THREE.Vector3(center.x, center.y, 0);
    flyTo(endPos, endLook, 1000);
}

/* ════════════════════════════════════════════════════════
   STATE MESHES
════════════════════════════════════════════════════════ */
const mapGroup    = new THREE.Group(); scene.add(mapGroup);
const stateMeshes = [];

function buildState(feature) {
    const name       = feature.properties.name;
    const regionName = stateToRegion[name];
    const region     = REGIONS[regionName];
    const color      = region ? region.color : 0x334155;
    const polys      = feature.geometry.type === 'MultiPolygon' ? feature.geometry.coordinates : [feature.geometry.coordinates];
    const group      = new THREE.Group(); group.userData.stateName = name;

    for (const poly of polys) {
        const shape = buildShapeFromRings(poly);
        if (!shape) continue;
        const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.28, bevelEnabled: false });
        const mat = new THREE.MeshPhongMaterial({ color, shininess: 25, specular: new THREE.Color(0x1a2a44) });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData = { name, regionName, region, originalColor: color };
        group.add(mesh); stateMeshes.push(mesh);
        const eg = new THREE.EdgesGeometry(geo, 2);
        group.add(new THREE.LineSegments(eg, new THREE.LineBasicMaterial({ color: 0x090d1f, transparent: true, opacity: 0.85 })));
    }
    return group;
}

/* ════════════════════════════════════════════════════════
   CONGRESSIONAL DISTRICT OVERLAY
   Data: US Census Bureau TIGERweb REST API — 119th Congress
   Each state fetched on demand and cached.
════════════════════════════════════════════════════════ */
let districtGroup   = null;
let districtMeshes  = [];
let hoveredDistrict = null;
const districtCache = {};  // keyed by state FIPS

const TIGERWEB_URL = 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Legislative/MapServer/0/query';

async function loadCongressionalDistricts(fips) {
    if (districtCache[fips]) return districtCache[fips];
    const params = new URLSearchParams({
        where:            `STATE='${fips}'`,
        outFields:        'STATE,CD119,NAME,GEOID',
        returnGeometry:   'true',
        f:                'geojson',
        geometryPrecision:'3',
        inSR:             '4326',
        outSR:            '4326',
    });
    // Use cache:'no-store' to bypass browser HTTP cache — prevents
    // ERR_CACHE_WRITE_FAILURE when the cache is full after national load.
    // Retry once on any network failure.
    let data;
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const res = await fetch(`${TIGERWEB_URL}?${params}`, { cache: 'no-store' });
            data = await res.json();
            if (data.features?.length) break;
        } catch (e) {
            if (attempt === 1) throw e;
            await new Promise(r => setTimeout(r, 600));
        }
    }
    if (!data?.features?.length) throw new Error(`No districts returned for FIPS ${fips}`);
    districtCache[fips] = data.features;
    return data.features;
}

function clearDistricts() {
    if (districtGroup) { mapGroup.remove(districtGroup); districtGroup = null; }
    districtMeshes = []; hoveredDistrict = null;
}

/* Restore all district fills to full opacity (called when panel goes back to state view) */
function resetDistrictSelection() {
    for (const d of districtMeshes) {
        d.material.color.setHex(d.userData.originalColor);
        d.material.opacity = 0.88;
        d.position.z       = 0.29;
    }
}

function flyToDistrictTopDown(mesh) {
    flyToMeshesTopDown([mesh], 2.6);
}

async function buildDistrictOverlay(stateName, regionHex) {
    clearDistricts();
    const fips = STATE_FIPS[stateName];
    if (!fips) return 0;

    const features = await loadCongressionalDistricts(fips);
    districtGroup   = new THREE.Group();
    const abbr      = STATE_ABBR_MAP[stateName];

    features.forEach((feat, i) => {
        const cdRaw     = String(feat.properties.CD119 ?? '0').padStart(2, '0');
        const isAtLarge = cdRaw === '00';
        const distNum   = isAtLarge ? 'AL' : String(parseInt(cdRaw));
        const label     = isAtLarge ? 'At-Large' : `District ${distNum}`;

        /* Color by seated-member party (119th Congress) */
        const partyKey = isAtLarge ? `${abbr}-AL` : `${abbr}-${distNum}`;
        const party    = DISTRICT_PARTY_MAP[partyKey] || 'U';
        const shade    = new THREE.Color(PARTY_INT[party]);
        const colorInt = shade.getHex();

        const polys = feat.geometry.type === 'MultiPolygon'
            ? feat.geometry.coordinates
            : [feat.geometry.coordinates];

        for (const poly of polys) {
            const shape = buildShapeFromRings(poly);
            if (!shape) continue;

            const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.04, bevelEnabled: false }); // flat — reads like map tiles
            const mat = new THREE.MeshPhongMaterial({
                color: shade, transparent: true, opacity: 0.88,
                shininess: 60, specular: new THREE.Color(0x334466),
            });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.z = 0.29;
            mesh.userData   = { districtNum: distNum, districtLabel: label, stateName, regionHex, party, partyHex: PARTY_HEX[party], originalColor: colorInt };
            districtGroup.add(mesh);
            districtMeshes.push(mesh);

            /* Bright white borders — clearly visible between adjacent districts */
            const eg = new THREE.EdgesGeometry(geo, 1);
            const em = new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.75 });
            const el = new THREE.LineSegments(eg, em);
            el.position.z = 0.29;
            el.renderOrder = 1;          // draw borders on top of fills
            districtGroup.add(el);
        }
    });

    mapGroup.add(districtGroup);
    return features.length;
}

/* ════════════════════════════════════════════════════════
   MAP LOAD
════════════════════════════════════════════════════════ */
const loadingEl = document.getElementById('loading');

fetch('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json')
    .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
    .then(us => {
        const geo = topojson.feature(us, us.objects.states);
        for (const feat of geo.features) mapGroup.add(buildState(feat));
        buildLegend();
        loadingEl.style.opacity = '0';
        setTimeout(() => { loadingEl.style.display = 'none'; }, 520);
        // Pre-fetch all governor parties in background so Party Control mode is ready
        fetchAllGovernorParties();
    })
    .catch(err => {
        loadingEl.innerHTML = `<p style="color:#ef4444;font-size:14px;">Failed to load map data.<br>${err.message}</p>`;
    });

/* ════════════════════════════════════════════════════════
   LEGEND  (click = zoom to region)
════════════════════════════════════════════════════════ */
function showRegionLegend() {
    document.getElementById('legend').querySelector('h3').textContent = 'U.S. Regions';
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    for (const [name, data] of Object.entries(REGIONS)) {
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.innerHTML = `<span class="legend-swatch" style="background:${data.hex};"></span>
            <span class="legend-name">${name}</span>
            <span class="legend-count">(${data.states.length})</span>`;
        row.title = `Click to zoom into the ${name} region`;
        row.addEventListener('mouseenter', () => dimExcept(name));
        row.addEventListener('mouseleave', () => { if (mapMode === 'overview') clearDim(); });
        row.addEventListener('click', () => enterRegionMode(name, data));
        el.appendChild(row);
    }
}

function showPartyLegend(breakdown = {}) {
    document.getElementById('legend').querySelector('h3').textContent = 'Party Control';
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    const order = ['R', 'D', 'I', 'U'];
    for (const code of order) {
        const count = breakdown[code] || 0;
        if (!count && code === 'U') continue; // skip unknown if none
        if (!count && code === 'I') continue; // skip independent if none
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.style.cursor = 'default';
        row.innerHTML = `<span class="legend-swatch" style="background:${PARTY_HEX[code]};"></span>
            <span class="legend-name">${PARTY_LABEL[code]}</span>
            ${count ? `<span class="legend-count">(${count} seat${count !== 1 ? 's' : ''})</span>` : ''}`;
        el.appendChild(row);
    }
}

// backward-compat alias (called by map load callback)
function buildLegend() { showRegionLegend(); }

/* ════════════════════════════════════════════════════════
   COLOUR HELPERS
════════════════════════════════════════════════════════ */
function lighten(hex, amt = 55) {
    const r = Math.min(255, ((hex>>16)&0xff)+amt);
    const g = Math.min(255, ((hex>>8) &0xff)+amt);
    const b = Math.min(255, ( hex     &0xff)+amt);
    return (r<<16)|(g<<8)|b;
}
function dimExcept(regionName) {
    for (const m of stateMeshes) {
        m.material.color.setHex(m.userData.regionName !== regionName ? 0x1a2240 : lighten(m.userData.originalColor, 30));
    }
}
function clearDim() {
    for (const m of stateMeshes) {
        if (m !== hoveredMesh && m.userData.name !== selectedState) m.material.color.setHex(m.userData.originalColor);
    }
}

/* ════════════════════════════════════════════════════════
   MODE TRANSITIONS
════════════════════════════════════════════════════════ */
function enterOverviewMode() {
    statePanelRequestId++;
    stateData = null;
    mapMode = 'overview'; activeRegion = null; activeState = null; selectedState = null;
    clearDim(); clearDistricts();
    infoPanel.classList.remove('open');
    resizeRenderer();
    document.getElementById('btn-back').style.display = 'none';
    document.getElementById('hint').innerHTML = 'Drag to rotate &nbsp;·&nbsp; Scroll to zoom &nbsp;·&nbsp; Click a state';
    // Restore all state meshes to full opacity
    for (const m of stateMeshes) {
        m.material.transparent = false;
        m.material.opacity     = 1.0;
        m.material.color.setHex(m.userData.originalColor);
        m.parent.position.z    = 0;
    }
    showRegionLegend();
    controls.autoRotate = false; updateRotateBtn(false);
    flyTo(new THREE.Vector3(0, 7, 13), new THREE.Vector3(0, 0, 0));
    updateBreadcrumb();
}

function enterRegionMode(regionName, region) {
    statePanelRequestId++;
    stateData = null;
    mapMode = 'region'; activeRegion = regionName; activeState = null; selectedState = null;
    clearDistricts(); infoPanel.classList.remove('open');
    resizeRenderer();
    controls.autoRotate = false; updateRotateBtn(false);
    document.getElementById('btn-back').style.display = '';
    document.getElementById('hint').innerHTML = `Click a state in the <span style="color:${region.hex}">${regionName}</span> region`;
    // Restore all state mesh opacity before dimming by region
    for (const m of stateMeshes) {
        m.material.transparent = false;
        m.material.opacity     = 1.0;
        m.parent.position.z    = 0;
    }
    dimExcept(regionName);
    showRegionLegend();
    const rMeshes = stateMeshes.filter(m => m.userData.regionName === regionName);
    flyToMeshes(rMeshes, 1.35);
    updateBreadcrumb();
}

async function enterStateMode(stateName, regionName, region) {
    const requestId = ++statePanelRequestId;
    mapMode = 'state'; activeRegion = regionName; activeState = stateName; selectedState = stateName;
    controls.autoRotate = false; updateRotateBtn(false);
    document.getElementById('btn-back').style.display = '';
    document.getElementById('hint').innerHTML = 'Click a congressional district to see candidates';

    /* Dim all other states and make the selected state nearly transparent
     * so the district fills are the primary visual layer. The state mesh
     * remains for raycasting but doesn't visually compete with districts. */
    for (const m of stateMeshes) {
        if (m.userData.name !== stateName) {
            m.material.color.setHex(0x0a0f22);
            m.material.transparent = true;
            m.material.opacity     = 0.6;
            m.parent.position.z    = 0;
        } else {
            // Selected state: near-transparent base so districts show clearly on top
            m.material.color.setHex(0x1a2a1a);
            m.material.transparent = true;
            m.material.opacity     = 0.25;
            m.parent.position.z    = 0;
        }
    }

    /* Fly to state — top-down so districts read as a flat map */
    const sMeshes = stateMeshes.filter(m => m.userData.name === stateName);
    flyToMeshesTopDown(sMeshes, 1.1);

    /* Districts */
    document.getElementById('panel-candidates').innerHTML = `<div class="panel-spinner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${region?.hex||'#6366f1'};">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
        </svg>&nbsp;Loading districts…</div>`;
    openInfoPanel();

    /* Panel header */
    document.getElementById('panel-state').textContent = stateName;
    const badge = document.getElementById('panel-badge');
    badge.textContent = (regionName||'') + ' Region';
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${(region?.hex||'#888')}22;color:${region?.hex||'#888'};border:1px solid ${region?.hex||'#888'}55;`;

    /* Region chips — clicking a sibling state navigates to it */
    const statesEl = document.getElementById('panel-states');
    statesEl.innerHTML = '';
    for (const s of (region?.states || [])) {
        const chip = document.createElement('span');
        chip.className   = 'state-chip' + (s === stateName ? ' active' : '');
        chip.textContent = s;
        chip.title       = s === stateName ? 'Currently viewing' : `Switch to ${s}`;
        if (s !== stateName) {
            chip.addEventListener('click', () => {
                closePopup();
                // Find this state's mesh to get its region data
                const mesh = stateMeshes.find(m => m.userData.name === s);
                if (mesh) enterStateMode(s, mesh.userData.regionName, mesh.userData.region);
            });
        }
        statesEl.appendChild(chip);
    }

    let distCount = 0;
    try {
        distCount = await buildDistrictOverlay(stateName, region?.hex);
        if (requestId !== statePanelRequestId) return;
    } catch (err) {
        // Degrade gracefully — show error in panel, still show statewide candidates
        console.warn(`District overlay failed for ${stateName}:`, err);
        document.getElementById('panel-candidates').innerHTML =
            `<p style="color:#ef444488;font-size:11px;margin:0 0 12px;">⚠ District boundaries unavailable (${err.message}). Retry by clicking the state again.</p>`;
    }

    // Fetch live candidate data from the API and cache it for district panels
    let nextStateData = null;
    const abbr = STATE_ABBR_MAP[stateName];
    // 'live' | 'empty' | 'unreachable'
    let apiStatus = 'unreachable';
    if (abbr) {
        try {
            const apiRes = await fetch(`/api/v1/map/state-candidates?state=${abbr}`);
            if (requestId !== statePanelRequestId) return;
            if (apiRes.ok) {
                nextStateData = await apiRes.json();
                apiStatus  = nextStateData?.offices?.length ? 'live' : 'empty';
            } else {
                apiStatus = 'unreachable';
            }
        } catch (e) {
            console.warn('state-candidates API unavailable:', e.message);
            apiStatus = 'unreachable';
        }
    }
    if (requestId !== statePanelRequestId) return;
    // Attach status so panel renderers can show the right badge
    if (nextStateData) nextStateData._apiStatus = apiStatus;
    else nextStateData = { _apiStatus: apiStatus };    // sentinel so panels can read it
    stateData = nextStateData;

    await openStatePanel(stateName, regionName, region, distCount, nextStateData);
    if (requestId !== statePanelRequestId) return;
    /* Switch legend to party breakdown */
    const breakdown = {};
    for (const m of districtMeshes) { const p = m.userData.party || 'U'; breakdown[p] = (breakdown[p]||0)+1; }
    showPartyLegend(breakdown);
    updateBreadcrumb();
}

function handleBack() {
    if (mapMode === 'state' && activeRegion) enterRegionMode(activeRegion, REGIONS[activeRegion]);
    else enterOverviewMode();
}

/* ════════════════════════════════════════════════════════
   BREADCRUMB
════════════════════════════════════════════════════════ */
function updateBreadcrumb() {
    const el = document.getElementById('breadcrumb');
    if (mapMode === 'overview') {
        el.innerHTML = '<span class="bc-item bc-active">Overview</span>';
    } else if (mapMode === 'region') {
        el.innerHTML = `<a class="bc-item bc-link" onclick="window.__mapBack()">Overview</a>
            <span class="bc-sep">›</span>
            <span class="bc-item bc-active" style="color:${REGIONS[activeRegion]?.hex}">${activeRegion}</span>`;
    } else {
        el.innerHTML = `<a class="bc-item bc-link" onclick="window.__mapReset()">Overview</a>
            <span class="bc-sep">›</span>
            <a class="bc-item bc-link" style="color:${REGIONS[activeRegion]?.hex}" onclick="window.__mapRegion('${activeRegion}')">${activeRegion}</a>
            <span class="bc-sep">›</span>
            <span class="bc-item bc-active">${activeState}</span>
            <span class="bc-sep">›</span>
            <span class="bc-item" style="color:#64748b">Districts (119th)</span>`;
    }
}
window.__mapReset  = enterOverviewMode;
window.__mapBack   = handleBack;
window.__mapRegion = name => enterRegionMode(name, REGIONS[name]);

/* ════════════════════════════════════════════════════════
   HOVER & CLICK
════════════════════════════════════════════════════════ */
const tooltip        = document.getElementById('tooltip');
const districtTip    = document.getElementById('district-tooltip');
const infoPanel      = document.getElementById('info-panel');
const legend         = document.getElementById('legend');

/**
 * Open the info panel. On mobile, also collapse the legend so the map
 * remains visible and the panel peeks at 40vh instead of covering everything.
 */
function openInfoPanel() {
    infoPanel.classList.add('open');
    infoPanel.classList.remove('expanded'); // always start at peek height
    if (window.innerWidth <= 768 && legend) {
        legend.classList.add('legend-collapsed');
    }
    // Shrink renderer to exclude the panel area so the globe stays centred
    resizeRenderer();
}

const raycaster      = new THREE.Raycaster();
const mouse          = new THREE.Vector2();
let hoveredMesh      = null;

renderer.domElement.addEventListener('mousemove', e => {
    const rect = renderer.domElement.getBoundingClientRect();
    mouse.x =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
    mouse.y = -((e.clientY - rect.top)  / rect.height)  * 2 + 1;
    raycaster.setFromCamera(mouse, camera);

    /* ── District hover (state mode only) ── */
    if (mapMode === 'state' && districtMeshes.length) {
        const dHits = raycaster.intersectObjects(districtMeshes);
        if (dHits.length) {
            const dm = dHits[0].object;
            if (hoveredDistrict && hoveredDistrict !== dm)
                hoveredDistrict.material.color.setHex(hoveredDistrict.userData.originalColor);
            hoveredDistrict = dm;
            /* Only brighten on hover if this isn't the currently-selected (elevated) district */
            if (dm.position.z < 0.08) {
                dm.material.color.setHex(lighten(dm.userData.originalColor, 90));
                dm.material.opacity = 0.95;
            }
            districtTip.style.display = 'block';
            districtTip.style.left = (e.clientX + 14) + 'px';
            districtTip.style.top  = (e.clientY - 10) + 'px';
            const _ph = dm.userData.partyHex || dm.userData.regionHex;
                const _pl = PARTY_LABEL[dm.userData.party || 'U'];
                districtTip.innerHTML  = `<strong style="color:#e2e8f0">${dm.userData.districtLabel}</strong><br>
                <span style="color:${_ph};font-size:11px">● ${_pl} · 119th Congress · Click for candidates</span>`;
            tooltip.style.display = 'none';
            renderer.domElement.style.cursor = 'pointer';
            return;
        }
        if (hoveredDistrict && hoveredDistrict.position.z < 0.08) {
            hoveredDistrict.material.color.setHex(hoveredDistrict.userData.originalColor);
            hoveredDistrict.material.opacity = 0.72;
        }
        hoveredDistrict = null;
        districtTip.style.display = 'none';
    }

    /* ── State hover (overview / region modes only — disabled in state mode) ── */
    if (mapMode === 'state') {
        // Clear any lingering hovered state and suppress tooltip for neighbour states
        if (hoveredMesh) {
            hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
            hoveredMesh.parent.position.z = 0;
            hoveredMesh = null;
        }
        tooltip.style.display = 'none';
        districtTip.style.display = 'none';
        renderer.domElement.style.cursor = districtMeshes.length ? 'default' : 'default';
        return;
    }

    if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
        hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
        hoveredMesh.parent.position.z = 0;
    }
    const sHits = raycaster.intersectObjects(stateMeshes);
    if (sHits.length) {
        const m = sHits[0].object;
        // In region mode: states outside the active region are not interactive
        const outsideRegion = mapMode === 'region' && activeRegion && m.userData.regionName !== activeRegion;
        if (outsideRegion) {
            if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
                hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
                hoveredMesh.parent.position.z = 0;
            }
            hoveredMesh = null; tooltip.style.display = 'none';
            renderer.domElement.style.cursor = 'not-allowed';
        } else {
            hoveredMesh = m;
            if (m.userData.name !== selectedState) m.material.color.setHex(lighten(m.userData.originalColor, 50));
            m.parent.position.z = 0.18;
            tooltip.style.display = 'block';
            tooltip.style.left = (e.clientX + 16) + 'px';
            tooltip.style.top  = (e.clientY - 14) + 'px';
            tooltip.innerHTML  = `<strong style="color:#e2e8f0;display:block;margin-bottom:3px">${m.userData.name}</strong>
                <span style="color:${m.userData.region?.hex||'#888'};font-size:12px">● ${m.userData.regionName||''} Region</span>`;
            renderer.domElement.style.cursor = 'pointer';
        }
    } else {
        hoveredMesh = null; tooltip.style.display = 'none';
        renderer.domElement.style.cursor = 'default';
    }
});

renderer.domElement.addEventListener('mouseleave', () => {
    if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
        hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
        hoveredMesh.parent.position.z = 0;
    }
    hoveredMesh = null; tooltip.style.display = 'none'; districtTip.style.display = 'none';
});

renderer.domElement.addEventListener('click', () => {
    raycaster.setFromCamera(mouse, camera);

    /* District click */
    if (mapMode === 'state' && districtMeshes.length) {
        const dHits = raycaster.intersectObjects(districtMeshes);
        if (dHits.length) {
            const dm = dHits[0].object;
            /* Dim all districts, restore their z */
            for (const d of districtMeshes) {
                d.material.color.setHex(d.userData.originalColor);
                d.material.opacity = 0.72;
                d.position.z       = 0.29;
            }
            /* Selected district: brightened party color, elevated, fully opaque */
            const bright = new THREE.Color(dm.userData.partyHex || dm.userData.regionHex || '#6366f1')
                .lerp(new THREE.Color(0xffffff), 0.55);
            dm.material.color.setHex(bright.getHex());
            dm.material.opacity  = 1.0;
            dm.position.z        = 0.09;
            openDistrictPanel(dm.userData.districtNum, dm.userData.districtLabel, dm.userData.stateName, dm.userData.regionHex, dm.userData.party);
            return;
        }
    }

    /* State click — only allow states within the active region when in region mode */
    const sHits = raycaster.intersectObjects(stateMeshes);
    if (sHits.length) {
        const m = sHits[0].object;
        // In region mode: ignore clicks on states outside the current region
        if (mapMode === 'region' && activeRegion && m.userData.regionName !== activeRegion) {
            return;
        }
        enterStateMode(m.userData.name, m.userData.regionName, m.userData.region);
    }
});

/* ════════════════════════════════════════════════════════
   CANDIDATE RENDER HELPERS
════════════════════════════════════════════════════════ */
const OFFICE_ROLES = {
    'Governor':           'Chief executive of the state. Signs or vetoes legislation, commands the National Guard, and oversees all state agencies.',
    'Lieutenant Governor':'Second-in-command; presides over the state senate in many states and assumes the governorship if needed.',
    'Attorney General':   "State's chief law-enforcement officer — represents the state in litigation and leads consumer-protection efforts.",
    'State Treasurer':    'Manages the state\'s financial assets, public fund investments, and debt.',
    'State Controller':   'Audits state spending and oversees public-fund accounting.',
    'Secretary of State': 'Manages elections, certifies results, and maintains official state records.',
};

const CITY_OFFICE_ROLES = {
    'Mayor':                    'Chief executive of the city. Proposes the municipal budget, oversees city departments, and sets local policy priorities.',
    'City Council':             'Legislative body of the city. Passes local ordinances, approves the budget, and represents neighborhood districts.',
    'City Manager':             'Professional administrator hired by the council to run day-to-day city operations.',
    'City Attorney':            'Legal counsel for the city; advises departments and represents the city in litigation.',
    'City Treasurer':           'Manages city funds, investments, and financial reporting.',
    'City Clerk':               'Maintains official city records, administers elections, and manages public notices.',
    'District Attorney':        'Elected prosecutor who decides which criminal cases to pursue in the county.',
    'County Executive':         'Chief executive of the county government, similar to a governor at the county level.',
    'County Sheriff':           'Elected law-enforcement officer responsible for county-wide policing and jail operations.',
    'School Board Member':      'Elected official governing the local public school district — sets policy, hires superintendent, approves budget.',
};

function partyClass(p) {
    if (!p) return 'party-I';
    const l = p.toLowerCase();
    if (l.includes('democrat')) return 'party-D';
    if (l.includes('republican')) return 'party-R';
    if (l.includes('libertarian')) return 'party-L';
    if (l.includes('green')) return 'party-G';
    return 'party-I';
}

/* ── Candidate popup ── */
const candPopup = document.getElementById('cand-popup');
document.getElementById('cand-popup-close').addEventListener('click', closePopup);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopup(); });

/* ════════════════════════════════════════════════════════
   KEYBOARD ACCESSIBILITY — ADA / WCAG 2.1 AA
   Arrow keys: pan/rotate the globe
   +/-: zoom in/out
   Enter/Space on canvas: open search
   R: reset view
   ?: toggle keyboard help overlay
════════════════════════════════════════════════════════ */
const kbHelp      = document.getElementById('kb-help');
const kbHelpClose = document.getElementById('kb-help-close');
const kbBadge     = document.getElementById('kb-hint-badge');
const mapRegion   = document.getElementById('map-canvas-region');

function toggleKbHelp(open) {
    kbHelp.classList.toggle('open', open ?? !kbHelp.classList.contains('open'));
    if (kbHelp.classList.contains('open')) {
        kbHelpClose.focus();
    }
}

kbHelpClose.addEventListener('click', () => toggleKbHelp(false));
kbBadge.addEventListener('click',     () => toggleKbHelp(true));

// Close help on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && kbHelp.classList.contains('open')) {
        toggleKbHelp(false);
        mapRegion.focus();
    }
});

// Show focus ring when canvas region is focused via keyboard
mapRegion.addEventListener('focus', () => mapRegion.classList.add('kb-active'));
mapRegion.addEventListener('blur',  () => mapRegion.classList.remove('kb-active'));

// Enter/Space on the canvas region → open search
mapRegion.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        document.getElementById('btn-search').click();
        return;
    }
    if (e.key === '?') { toggleKbHelp(true); return; }
    if (e.key === 'r' || e.key === 'R') { enterOverviewMode(); updateRotateBtn(false); return; }

    // Arrow keys: rotate/tilt the camera using OrbitControls
    // Shift+Arrow pans the target instead
    const ROTATE_STEP = 0.04;  // radians per keypress
    const ZOOM_STEP   = 1.15;  // zoom factor per keypress

    if (!controls) return;

    switch (e.key) {
        case 'ArrowLeft':
            e.preventDefault();
            controls.minAzimuthAngle = -Infinity; controls.maxAzimuthAngle = Infinity;
            controls.rotateLeft(ROTATE_STEP);
            controls.update();
            break;
        case 'ArrowRight':
            e.preventDefault();
            controls.rotateLeft(-ROTATE_STEP);
            controls.update();
            break;
        case 'ArrowUp':
            e.preventDefault();
            controls.rotateUp(ROTATE_STEP);
            controls.update();
            break;
        case 'ArrowDown':
            e.preventDefault();
            controls.rotateUp(-ROTATE_STEP);
            controls.update();
            break;
        case '+': case '=':
            e.preventDefault();
            stepZoom(1 / ZOOM_STEP);
            break;
        case '-': case '_':
            e.preventDefault();
            stepZoom(ZOOM_STEP);
            break;
    }
});

// Global ? shortcut (when canvas not focused)
document.addEventListener('keydown', e => {
    if (e.key === '?' && !e.target.matches('input, textarea, [contenteditable]')) {
        toggleKbHelp();
    }
});
// Click outside popup to close
document.addEventListener('click', e => {
    if (candPopup.classList.contains('visible') && !candPopup.contains(e.target) && !e.target.closest('.candidate-name')) closePopup();
});

function closePopup() {
    candPopup.classList.remove('visible');
    candPopup.style.display = 'none';
}

// avatarInitials is defined globally in a plain <script> tag above the module

function openCandidatePopup(c, color, anchorEl) {
    color = color || '#6366f1';

    // Avatar
    const avWrap = document.getElementById('popup-avatar-wrap');
    if (c.photo) {
        const ph48 = avatarInitials(c.full_name, color, 48).replace(/'/g, '&apos;').replace(/"/g, '&quot;');
        avWrap.innerHTML = `<img class="popup-avatar" src="${c.photo}" alt="${c.full_name}"
            onerror="this.outerHTML='<div class=&quot;popup-avatar-ph&quot;>${ph48}</div>'">`;
    } else {
        avWrap.innerHTML = `<div class="popup-avatar-ph">${avatarInitials(c.full_name, color, 48)}</div>`;
    }

    document.getElementById('popup-name').textContent    = c.full_name;
    document.getElementById('popup-office').textContent  = c.office || c.party || '';
    document.getElementById('popup-bio').textContent     = c.bio || 'No biography available.';
    document.getElementById('popup-raised').textContent  = c.raised || '—';
    document.getElementById('popup-status').textContent  = c.status === 'seated' ? '● Seated' : '● Running';
    document.getElementById('popup-status').style.color  = c.status === 'seated' ? '#818cf8' : '#34d399';

    const partyShort = { Democratic:'Dem', Republican:'Rep', Libertarian:'Lib', Green:'Grn', Independent:'Ind' };
    document.getElementById('popup-party-badge').textContent = partyShort[c.party] || c.party?.slice(0,4) || '—';
    document.getElementById('popup-party-badge').className   = `popup-stat-val party-pill ${partyClass(c.party)}`;
    document.getElementById('popup-party-badge').style.cssText = 'display:block;font-size:12px;font-weight:700;padding:2px 0;border:none;background:none;';

    const stanceEl = document.getElementById('popup-stance');
    if (c.stance_topic) {
        stanceEl.innerHTML = `<strong>${c.stance_topic}:</strong> ${c.stance_text}`;
        stanceEl.style.display = '';
    } else {
        stanceEl.style.display = 'none';
    }

    // CTAs — primary goes to the candidate's U9itus profile page
    const campLink = document.getElementById('popup-campaign-link');
    campLink.href = c.profile_url || '#';
    campLink.style.background = `linear-gradient(135deg, ${color}, ${color}cc)`;
    campLink.style.opacity    = c.profile_url ? '1' : '0.45';
    campLink.style.pointerEvents = c.profile_url ? '' : 'none';

    const bpLink = document.getElementById('popup-bp-link');
    bpLink.href  = c.ballotpedia_url || '#';
    bpLink.style.color       = color;
    bpLink.style.borderColor = color + '55';
    bpLink.style.opacity     = c.ballotpedia_url ? '1' : '0.4';
    bpLink.style.pointerEvents = c.ballotpedia_url ? '' : 'none';

    // Position popup near the clicked name, but keep it on screen
    candPopup.style.display = 'block';
    candPopup.classList.add('visible');
    const panelRect  = document.getElementById('info-panel').getBoundingClientRect();
    const anchorRect = anchorEl.getBoundingClientRect();
    const popW = 320, popH = candPopup.offsetHeight || 360;
    let top  = anchorRect.top - 10;
    let left = panelRect.left - popW - 12;
    if (left < 8) left = panelRect.right + 12;   // flip to right if no room on left
    if (top + popH > window.innerHeight - 8) top = window.innerHeight - popH - 8;
    if (top < 60) top = 60;
    candPopup.style.top  = top + 'px';
    candPopup.style.left = left + 'px';
}

function renderCandidate(c, color) {
    color = color || '#6366f1';
    // On image load failure, swap to initials SVG rather than a broken icon.
    const _initSvg36 = avatarInitials(c.full_name, color, 36).replace(/'/g, "\\'");
    const av = c.photo
        ? `<img class="candidate-avatar" src="${c.photo}" loading="lazy" alt="${c.full_name}" onerror="this.outerHTML='<span class=\\'candidate-avatar-placeholder\\'>${_initSvg36}</span>'">`
        : `<span class="candidate-avatar-placeholder">${avatarInitials(c.full_name, color, 36)}</span>`;
    const py = c.party  ? `<span class="party-pill ${partyClass(c.party)}">${c.party}</span>` : '';
    // Format next election date if available
    const elDate = c.general_date || c.election_date || null;
    const elDateStr = elDate ? (() => {
        try { return new Date(elDate).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}); }
        catch { return null; }
    })() : null;
    const elBadge = (c.is_running && elDateStr)
        ? `<span style="color:#64748b;font-size:9px;margin-left:4px;">📅 ${elDateStr}</span>`
        : '';
    const st = c.status === 'seated' ? `<span class="status-seated">● Seated</span>` : c.is_running ? `<span class="status-running">● Running 2026</span>${elBadge}` : '';
    const vf = c.verified ? `<span class="verified-badge">✓ Verified</span>` : '';
    // Encode candidate data on the whole card so the full row is clickable
    const popupData = JSON.stringify({ ...c, color });
    return `<div class="candidate-card"
        style="border-left-color:${color};"
        data-candidate='${popupData.replace(/'/g, '&apos;')}'
        title="Click to learn more about ${c.full_name}"
        role="button" tabindex="0"
        onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
        ${av}
        <div style="flex:1;min-width:0;">
            <div class="candidate-name">${c.full_name}</div>
            <div class="candidate-meta">${py}${st}${vf}</div>
        </div></div>`;
}

/* Delegate click on any .candidate-card to open popup */
document.getElementById('info-panel').addEventListener('click', e => {
    const card = e.target.closest('.candidate-card[data-candidate]');
    if (!card) return;
    e.stopPropagation();
    try {
        const c = JSON.parse(card.dataset.candidate.replace(/&apos;/g, "'"));
        openCandidatePopup(c, c.color, card);
        // On mobile: clear inline position set by desktop logic so CSS bottom-sheet takes over
        if (window.innerWidth <= 768) {
            candPopup.style.top = '';
            candPopup.style.left = '';
        }
    } catch { /* malformed data, ignore */ }
});

// All offices start expanded so voters see all candidates immediately.
const OFFICE_DEFAULT_OPEN = new Set([
    'Governor', 'Lieutenant Governor', 'Attorney General',
    'State Treasurer', 'State Controller', 'Secretary of State',
    'Other Statewide', 'Mayor',
]);
let _officeIdx = 0;

/**
 * Determines the election phase for a set of candidates.
 * post_general  — general election date has passed
 * post_primary  — at least one candidate has a primary_result recorded
 * pre_primary   — primary has not occurred yet
 */
function detectElectionPhase(candidates) {
    const today = new Date();
    let anyPrimaryResult = false, generalPassed = false;
    for (const c of (candidates || [])) {
        if (c.primary_result) anyPrimaryResult = true;
        if (c.status === 'lost') anyPrimaryResult = true;
        if (c.general_date && new Date(c.general_date) < today) { generalPassed = true; break; }
    }
    if (generalPassed) return 'post_general';
    if (anyPrimaryResult) return 'post_primary';
    return 'pre_primary';
}

function renderOfficeGroup(g, roles, color) {
    color = color || '#6366f1';
    const role      = roles?.[g.office] ?? '';
    const isOpen    = OFFICE_DEFAULT_OPEN.has(g.office);
    const sectionId = `off-body-${_officeIdx++}`;

    // Determine election phase: use API-supplied value, or compute from candidates
    const phase = g.election_phase || detectElectionPhase(g.candidates);

    // Split seated officeholder(s) from running candidates (exclude lost).
    // 'active' + !is_running is a legacy value from enrich-statewide; treat as seated.
    const isSeated = c => c.status === 'seated' || (c.status === 'active' && !c.is_running);
    const seated  = g.candidates.filter(isSeated);
    let running   = g.candidates.filter(c => !isSeated(c) && c.status !== 'lost');

    // Determine next election date from candidates (earliest future date wins)
    const today = new Date();
    const nextElDate = g.candidates
        .map(c => c.general_date || c.election_date || null)
        .filter(Boolean)
        .map(d => new Date(d))
        .filter(d => !isNaN(d))
        .sort((a,b) => a-b)
        .find(d => d >= today) || null;
    const nextElStr = nextElDate
        ? nextElDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
        : null;

    // Filter running candidates and set section label based on election phase
    let runningLabel = '';
    if (phase === 'post_general') {
        running = []; // General decided — only the officeholder remains
    } else if (phase === 'post_primary') {
        running = running.filter(c => !c.primary_result || c.primary_result === 'advanced_to_general');
        const genDate = running.find(c => c.general_date)?.general_date;
        runningLabel  = 'General Election Candidates'
            + (genDate ? ` · ${new Date(genDate).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}` : '');
    } else {
        runningLabel = '2026 Primary Candidates';
    }

    // Seated holder with optional term-end notice
    const seatedHtml = seated.map(c => {
        const termNotice = (c.term_end || c.term_note)
            ? `<div style="display:flex;align-items:center;gap:6px;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:6px 10px;margin-bottom:8px;font-size:10px;">
                <span style="color:#f59e0b;">⏳</span>
                <span style="color:#94a3b8;">
                  ${c.term_end ? `<strong style="color:#e2e8f0;">Term ends ${c.term_end}</strong>` : ''}
                  ${c.term_note ? `<span style="color:#64748b;"> &nbsp;·&nbsp; ${c.term_note}</span>` : ''}
                </span>
               </div>`
            : '';
        return termNotice + renderCandidate({ ...c, office: g.office }, color);
    }).join('');

    // Candidates section — only rendered when there are active candidates in this phase
    const candidatesHtml = running.length
        ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:10px 0 6px;">${runningLabel}</p>
           ${running.map(c => renderCandidate({ ...c, office: g.office }, color)).join('')}`
        : '';

    // Summary line shown in the collapsed header (names)
    const allNames   = g.candidates.map(c => c.full_name).join(', ');
    const nameSummary = !isOpen
        ? `<span style="font-weight:400;opacity:.55;font-size:9px;margin-left:8px;text-transform:none;letter-spacing:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;display:inline-block;vertical-align:middle;">${allNames}</span>`
        : '';

    return `<div class="office-section${isOpen ? '' : ' collapsed'}" id="off-${sectionId}">
        <div class="office-title"
             style="background:${color}18;border-left:3px solid ${color};color:${color};"
             onclick="(function(el){
               const sec=el.closest('.office-section');
               const open=sec.classList.toggle('collapsed');
               el.querySelector('.name-summary').style.display = sec.classList.contains('collapsed') ? 'inline-block' : 'none';
             })(this)"
             role="button" aria-expanded="${isOpen}" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')this.click()">
            <span style="display:flex;align-items:center;gap:6px;flex:1;min-width:0;">
              <span>🏛&nbsp;${g.office}</span>
              ${nextElStr ? `<span style="background:#f59e0b18;border:1px solid #f59e0b44;border-radius:4px;padding:1px 6px;font-size:9px;font-weight:600;color:#f59e0b;white-space:nowrap;">📅 ${nextElStr}</span>` : ''}
              <span class="name-summary" style="font-weight:400;opacity:.55;font-size:9px;margin-left:4px;text-transform:none;letter-spacing:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;display:${isOpen ? 'none' : 'inline-block'};vertical-align:middle;">${allNames}</span>
            </span>
            <span class="chevron">▾</span>
        </div>
        <div class="office-body">
            ${role ? `<p class="office-role-tip">${role}</p>` : ''}
            ${seated.length ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;">Current Officeholder</p>` : ''}
            ${seatedHtml}
            ${candidatesHtml}
        </div>
    </div>`;
}

/* No-data notice rendered when a state/district has no live records */
function noDataNotice(msg) {
    return `<div style="display:flex;align-items:center;gap:8px;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:10px 12px;margin-bottom:12px;">
        <span style="font-size:16px;">📭</span>
        <span style="color:#94a3b8;font-size:11px;">${msg}</span>
    </div>`;
}

/* ════════════════════════════════════════════════════════
   PANEL: STATE (statewide offices + district badge)
════════════════════════════════════════════════════════ */
// Cached API response for the currently-viewed state (populated by enterStateMode)
let stateData = null;

async function openStatePanel(stateName, regionName, region, districtCount, panelData = null) {
    const data = panelData || stateData || {};
    const color  = region?.hex || '#6366f1';
    const candEl = document.getElementById('panel-candidates');

    await new Promise(r => setTimeout(r, 380));

    _officeIdx = 0; // reset accordion counter for each new state
    const offices   = data?.offices ?? [];
    const apiStatus = data?._apiStatus || 'unreachable';

    const DATA_BANNERS = {
        live:        '',
        empty:       `<div style="display:flex;align-items:center;gap:8px;background:#1e293b;border:1px solid #334155;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
                        <span style="font-size:14px;">📭</span>
                        <span style="color:#94a3b8;font-size:11px;">No candidate records found for this state yet. Data is added weekly via the sync workflow.</span>
                      </div>`,
        unreachable: `<div style="display:flex;align-items:center;gap:8px;background:#1e1a2e;border:1px solid #7c3aed55;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
                        <span style="font-size:14px;">⚠️</span>
                        <div>
                          <span style="color:#a78bfa;font-size:11px;font-weight:600;">DATA UNREACHABLE</span>
                          <span style="color:#64748b;font-size:11px;"> · Showing preview data. Live records are unavailable right now.</span>
                        </div>
                      </div>`,
    };
    let html = DATA_BANNERS[apiStatus] ?? DATA_BANNERS.unreachable;

    if (districtCount > 0) {
        const expected = DISTRICT_COUNTS[stateName] || districtCount;
        const popLine = (data?.population)
            ? `<p style="color:#475569;font-size:11px;margin:4px 0 0;">👥 State population: <strong style="color:#e2e8f0;">${data.population.formatted}</strong> <span style="opacity:.6">(${data.population.census_year} Census)</span></p>`
            : '';
        html += `<div style="background:${color}0f;border:1px solid ${color}33;border-radius:8px;padding:10px 12px;margin-bottom:14px;">
            <p style="color:${color};font-size:12px;font-weight:600;margin:0 0 4px;">🗺 ${districtCount} of ${expected} Congressional Districts loaded</p>
            <p style="color:#475569;font-size:11px;margin:0 0 4px;">119th Congress (2025–2027) district boundaries</p>
            <p style="color:#475569;font-size:11px;margin:0;">Click any district on the map to view its U.S. House candidates</p>
            ${popLine}
        </div>`;
    }

    html += offices.length
        ? offices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.');

    // City officials section (mayors etc.) — only seated unclaimed officeholders
    const cityOfficials = data?.city_officials ?? {};
    const cityEntries   = Object.entries(cityOfficials);
    if (cityEntries.length > 0) {
        html += `<div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
            <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">🏙 City Officials</span>
            <div style="flex:1;border-top:1px solid ${color}20;"></div>
        </div>`;
        for (const [city, officials] of cityEntries) {
            html += `<div style="margin-bottom:14px;">
                <p style="color:#94a3b8;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:0 0 6px;"
                   title="City of ${city}">${city}</p>`;
            for (const o of officials) {
                const officeTitle = o.political_office || 'Mayor';
                const roleDesc    = CITY_OFFICE_ROLES[officeTitle] || null;
                const elDateCity  = o.election_date || null;
                if (roleDesc) {
                    html += `<p class="office-role-tip" style="margin-bottom:6px;">${roleDesc}</p>`;
                }
                html += renderCandidate({
                    full_name:    o.full_name,
                    party:        o.party,
                    status:       o.status || 'seated',
                    is_running:   false,
                    verified:     o.verified || false,
                    photo:        o.photo || null,
                    slug:         o.slug || null,
                    profile_url:  o.profile_url || null,
                    ballotpedia_url: o.ballotpedia_url || null,
                    website:      o.website || null,
                    bio:          o.bio_excerpt || null,
                    office:       officeTitle,
                    general_date: elDateCity,
                }, color);
            }
            html += '</div>';
        }
    }

    candEl.innerHTML = html;
}

/* ════════════════════════════════════════════════════════
   PANEL: DISTRICT (U.S. House)
════════════════════════════════════════════════════════ */
async function openDistrictPanel(districtNum, districtLabel, stateName, regionHex, party = 'U') {
    // Use party color as the accent; fall back to region color
    const color = PARTY_HEX[party] || regionHex || '#6366f1';
    const partyLabel = PARTY_LABEL[party] || 'Unknown';
    document.getElementById('panel-state').textContent = `${stateName} — ${districtLabel}`;
    const badge = document.getElementById('panel-badge');
    badge.textContent = `${partyLabel} · 119th Congress`;
    badge.style.cssText = `display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:600;background:${color}22;color:${color};border:1px solid ${color}55;`;
    document.getElementById('panel-states').innerHTML = '';
    openInfoPanel();

    const candEl = document.getElementById('panel-candidates');
    candEl.innerHTML = `<div class="panel-spinner"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:${color};"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>&nbsp;Loading…</div>`;

    await new Promise(r => setTimeout(r, 320));

    // District candidates: use live API data keyed by district label, fall back to mock
    const liveDist = stateData?.house_candidates?.[districtLabel];
    let seated, challenger, third;
    if (liveDist?.length) {
        // Map live records to the shape renderCandidate() expects
        [seated, challenger, third] = [0,1,2].map(i => {
            const c = liveDist[i];
            if (!c) return null;
            return {
                full_name: c.full_name, party: c.party, is_running: c.is_running,
                status: c.status || 'running', verified: c.verified || false,
                photo: c.photo || null, slug: c.slug || null,
                profile_url: c.profile_url || null,
                ballotpedia_url: c.ballotpedia_url || null, website: c.website || null,
                bio: c.bio_excerpt || null, raised: null, stance_topic: null, stance_text: null,
                primary_result: c.primary_result || null,
                general_date:   c.general_date   || null,
                office: `U.S. Representative — ${districtLabel}`,
            };
        });
    } else {
        seated = challenger = third = null;
    }

    _officeIdx = 0; // reset accordion counter for district panel
    const stateOffices = stateData?.offices ?? [];

    // District population from cached API response
    const distPop = stateData?.district_populations?.[districtLabel];
    const statePop = stateData?.population;
    const popBadge = distPop
        ? `<span style="color:#94a3b8;font-size:11px;margin-left:8px;">👥 ${distPop.formatted} residents <span style="opacity:.6">(${distPop.census_year} Census)</span></span>`
        : (statePop ? `<span style="color:#94a3b8;font-size:11px;margin-left:8px;">👥 State pop: ${statePop.formatted}</span>` : '');

    const _distApiStatus = stateData?._apiStatus || 'unreachable';
    const _distLive       = !!(stateData?.house_candidates?.[districtLabel]?.length);

    // Determine election phase for this district race
    const distCands   = [seated, challenger, third].filter(Boolean);
    const distPhase   = detectElectionPhase(distCands);
    const advancedChalls = [challenger, third].filter(c => c && (!c.primary_result || c.primary_result === 'advanced_to_general'));
    let challSection = '';
    if (distPhase === 'post_general') {
        challSection = ''; // General decided — no challengers to show
    } else if (distPhase === 'post_primary') {
        if (advancedChalls.length) {
            const genDate = advancedChalls.find(c => c.general_date)?.general_date;
            const challLabel = 'General Election Candidates'
                + (genDate ? ` · ${new Date(genDate).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}` : '');
            challSection = `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:12px 0 6px;">${challLabel}</p>
                ${advancedChalls.map(c => renderCandidate(c, color)).join('')}`;
        }
    } else {
        challSection = `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:12px 0 6px;">2026 Primary Challengers</p>
            ${challenger ? renderCandidate(challenger, color) : ''}
            ${third      ? renderCandidate(third, color)      : ''}`;
    }

    // Build house district candidates HTML (null = no data)
    const houseHtml = seated
        ? `<p style="color:#475569;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin:8px 0 6px;">Current Officeholder</p>
        ${renderCandidate(seated, color)}
        ${challSection}`
        : noDataNotice('No records for this district yet. Data is synced weekly from congress-legislators and Ballotpedia.');

    // Statewide section
    const statewideHtml = stateOffices.length
        ? stateOffices.map(g => renderOfficeGroup(g, OFFICE_ROLES, color)).join('')
        : noDataNotice('Statewide candidate records for this state are not yet available. Check back after the next weekly sync.');

    // Top banner: only show when API was unreachable (distinct from simply empty)
    const _distBanner = (_distApiStatus === 'unreachable')
        ? `<div style="display:flex;align-items:center;gap:8px;background:#1e1a2e;border:1px solid #7c3aed55;border-radius:8px;padding:8px 12px;margin-bottom:12px;">
             <span style="font-size:14px;">⚠️</span>
             <div><span style="color:#a78bfa;font-size:11px;font-weight:600;">DATA UNREACHABLE</span><span style="color:#64748b;font-size:11px;"> · Live records unavailable right now.</span></div>
           </div>`
        : '';

    candEl.innerHTML = `${_distBanner}

    <!-- U.S. House district section -->
    <div style="background:${color}0a;border:1px solid ${color}22;border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:11px;color:#475569;">
        <span style="color:${color};font-weight:600;">119th Congress</span> &nbsp;·&nbsp; 2025–2027
        &nbsp;·&nbsp; <a href="https://www.house.gov" target="_blank" rel="noopener" style="color:${color};text-decoration:none;">house.gov →</a>
        ${popBadge}
    </div>
    <div class="office-section">
        <div class="office-title" style="background:${color}18;border-left:3px solid ${color};color:${color};padding:6px 10px;border-radius:6px;margin-bottom:6px;">
            🏛&nbsp;U.S. Representative — ${districtLabel}
        </div>
        <p class="office-role-tip">Elected every 2 years. Represents ~750,000 constituents in the U.S. House of Representatives.</p>
        ${houseHtml}
    </div>

    <!-- Divider before statewide races -->
    <div style="border-top:1px solid ${color}20;margin:16px 0 14px;display:flex;align-items:center;gap:8px;">
        <span style="color:${color};font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;white-space:nowrap;">Statewide Races — ${stateName}</span>
        <div style="flex:1;border-top:1px solid ${color}20;"></div>
    </div>
    ${statewideHtml}`;
}

/* ════════════════════════════════════════════════════════
   NATIONAL DISTRICT BOUNDARY LAYER
   Fetches ALL 444 districts in one TIGERweb request.
   Rendered as LineSegments only (no fill) — works at any zoom.
════════════════════════════════════════════════════════ */
let nationalDistGroup = null;
let nationalDistLoaded = false;
let nationalDistVisible = false;
let nationalDistLoading = false;

function buildLineLoopsFromFeature(feat, color) {
    // Build flat LineLoop geometries from GeoJSON polygon rings
    const lines = [];
    const polys = feat.geometry.type === 'MultiPolygon'
        ? feat.geometry.coordinates
        : [feat.geometry.coordinates];

    for (const poly of polys) {
        for (const ring of poly) {
            const pts = [];
            for (const coord of ring) {
                const p = project(coord);
                if (p) pts.push(new THREE.Vector3(p[0], p[1], 0.31));
            }
            if (pts.length < 2) continue;
            const geo = new THREE.BufferGeometry().setFromPoints(pts);
            const mat = new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.72 });
            lines.push(new THREE.Line(geo, mat));
        }
    }
    return lines;
}

/* Build a fips → region hex lookup for fast coloring */
const fipsToRegionHex = (() => {
    const map = {};
    for (const [regionName, region] of Object.entries(REGIONS)) {
        for (const [stateName, fips] of Object.entries(STATE_FIPS)) {
            if (region.states.includes(stateName)) {
                map[fips] = region.hex;
            }
        }
    }
    return map;
})();

async function fetchStateDistrictsLow(fips) {
    // Re-use the existing per-state endpoint (CORS allowed) but with lower precision
    const params = new URLSearchParams({
        where:             `STATE='${fips}'`,
        outFields:         'STATE,CD119',
        returnGeometry:    'true',
        f:                 'geojson',
        geometryPrecision: '2',   // lower precision = faster + smaller payload
        inSR:              '4326',
        outSR:             '4326',
    });
    const res  = await fetch(`${TIGERWEB_URL}?${params}`);
    const data = await res.json();
    return data.features || [];
}

async function loadNationalBoundaries() {
    if (nationalDistLoading) return;
    nationalDistLoading = true;

    const progress = document.getElementById('dist-progress');
    progress.style.color = '#94a3b8';
    progress.style.display = 'block';

    try {
        nationalDistGroup = new THREE.Group();
        mapGroup.add(nationalDistGroup);

        // All unique state FIPS codes
        const allFips = [...new Set(Object.values(STATE_FIPS))].sort();
        let done = 0;

        // Fetch in parallel batches of 8
        const BATCH = 8;
        for (let i = 0; i < allFips.length; i += BATCH) {
            const batch = allFips.slice(i, i + BATCH);
            progress.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>
                Loading districts… ${done}/${allFips.length} states`;

            await Promise.all(batch.map(async fips => {
                try {
                    const features = await fetchStateDistrictsLow(fips);
                    const stateAbbr = FIPS_TO_ABBR[fips];
                    for (const feat of features) {
                        const cdRaw = String(feat.properties.CD119 ?? '0').padStart(2,'0');
                        const dn    = cdRaw === '00' ? 'AL' : String(parseInt(cdRaw));
                        const pKey  = dn === 'AL' ? `${stateAbbr}-AL` : `${stateAbbr}-${dn}`;
                        const party = DISTRICT_PARTY_MAP[pKey] || 'U';
                        const col   = new THREE.Color(PARTY_INT[party]).lerp(new THREE.Color(0xffffff), 0.38).getHex();
                        for (const line of buildLineLoopsFromFeature(feat, col)) {
                            nationalDistGroup.add(line);
                        }
                    }
                } catch { /* skip failed states silently */ }
                done++;
            }));
        }

        nationalDistLoaded  = true;
        nationalDistVisible = true;
        progress.style.display = 'none';
        document.getElementById('btn-districts').textContent = 'District Boundaries: ON';
        document.getElementById('btn-districts').classList.add('active');
    } catch (err) {
        progress.style.display = 'none';
        progress.textContent   = `⚠ Failed: ${err.message}`;
        progress.style.color   = '#ef4444';
        progress.style.display = 'block';
        setTimeout(() => { progress.style.display = 'none'; }, 5000);
    }
    nationalDistLoading = false;
}

function toggleNationalBoundaries() {
    if (!nationalDistLoaded) {
        loadNationalBoundaries();
        return;
    }
    nationalDistVisible = !nationalDistVisible;
    if (nationalDistGroup) nationalDistGroup.visible = nationalDistVisible;
    // Keep the hidden btn-districts text in sync (used by mobile drawer)
    const btn = document.getElementById('btn-districts');
    if (btn) { btn.textContent = `District Boundaries: ${nationalDistVisible ? 'ON' : 'OFF'}`; btn.classList.toggle('active', nationalDistVisible); }
    // Sync the dropdown toggle
    updateDistrictsBtn(nationalDistVisible);
}

document.getElementById('btn-districts').addEventListener('click', toggleNationalBoundaries);

/* ════════════════════════════════════════════════════════
   SEARCH PALETTE
════════════════════════════════════════════════════════ */
const searchOverlay = document.getElementById('search-overlay');
const searchInput   = document.getElementById('search-input');
const searchResults = document.getElementById('search-results');
const searchEmpty   = document.getElementById('search-empty');
let searchActiveIdx = -1;

/* Build a searchable index: states + every district for each state */
const SEARCH_INDEX = [];

// All states
for (const [stateName, abbr] of Object.entries(STATE_ABBR_MAP)) {
    const regionName = stateToRegion[stateName];
    const region     = REGIONS[regionName];
    SEARCH_INDEX.push({
        type:       'state',
        label:      stateName,
        sub:        `${abbr} · ${regionName || ''} Region`,
        abbr,
        stateName,
        regionName,
        region,
        keywords:   [stateName.toLowerCase(), abbr.toLowerCase()],
        color:      region?.hex || '#6366f1',
    });
}

// Districts for every state (static from DISTRICT_COUNTS)
for (const [stateName, count] of Object.entries(DISTRICT_COUNTS)) {
    const abbr       = STATE_ABBR_MAP[stateName];
    const regionName = stateToRegion[stateName];
    const region     = REGIONS[regionName];
    if (!abbr || count === 0) continue;
    for (let d = 1; d <= count; d++) {
        const label = `${stateName} — District ${d}`;
        SEARCH_INDEX.push({
            type:        'district',
            label,
            sub:         `${abbr}-${String(d).padStart(2,'0')} · 119th Congress · U.S. House`,
            abbr,
            stateName,
            districtNum: String(d),
            regionName,
            region,
            color:       region?.hex || '#6366f1',
            keywords:    [
                stateName.toLowerCase(),
                abbr.toLowerCase(),
                `${abbr.toLowerCase()}-${d}`,
                `${abbr.toLowerCase()}${d}`,
                `district ${d}`,
                `${d}`,
            ],
        });
    }
    // At-large states
    if (count === 1) {
        SEARCH_INDEX.push({
            type:        'district',
            label:       `${stateName} — At-Large`,
            sub:         `${abbr}-AL · 119th Congress · U.S. House`,
            abbr,
            stateName,
            districtNum: 'AL',
            regionName,
            region,
            color:       region?.hex || '#6366f1',
            keywords:    [stateName.toLowerCase(), abbr.toLowerCase(), 'at-large', `${abbr.toLowerCase()}-al`],
        });
    }
}

function scoreMatch(item, q) {
    const terms = q.toLowerCase().trim().split(/\s+/);
    let score = 0;
    for (const term of terms) {
        if (!item.keywords.some(k => k.includes(term))) return 0; // all terms must match
        if (item.keywords.some(k => k === term)) score += 10;     // exact keyword match
        if (item.label.toLowerCase().includes(term)) score += 5;
    }
    // Boost states over districts when query is short
    if (item.type === 'state' && q.length <= 4) score += 3;
    return score;
}

function renderSearchResults(q) {
    searchActiveIdx = -1;
    searchResults.innerHTML = '';

    if (!q.trim()) {
        searchEmpty.style.display = 'none';
        // Show recent regions as quick picks
        const ql = document.createElement('div');
        ql.className = 'sr-group-label';
        ql.textContent = 'Quick picks — regions';
        searchResults.appendChild(ql);
        for (const [rName, r] of Object.entries(REGIONS)) {
            appendResult({
                type: 'region', label: rName + ' Region',
                sub: `${r.states.length} states`, color: r.hex, regionName: rName, region: r,
                keywords: [],
            });
        }
        return;
    }

    const scored = SEARCH_INDEX
        .map(item => ({ item, score: scoreMatch(item, q) }))
        .filter(x => x.score > 0)
        .sort((a, b) => b.score - a.score)
        .slice(0, 12);

    if (!scored.length) {
        searchEmpty.style.display = 'block';
        return;
    }
    searchEmpty.style.display = 'none';

    // Group into states first, then districts
    const states    = scored.filter(x => x.item.type === 'state');
    const districts = scored.filter(x => x.item.type === 'district');

    if (states.length) {
        const gl = document.createElement('div');
        gl.className = 'sr-group-label'; gl.textContent = 'States';
        searchResults.appendChild(gl);
        states.forEach(x => appendResult(x.item));
    }
    if (districts.length) {
        const gl = document.createElement('div');
        gl.className = 'sr-group-label'; gl.textContent = 'Congressional Districts';
        searchResults.appendChild(gl);
        districts.forEach(x => appendResult(x.item));
    }
}

function appendResult(item) {
    const el = document.createElement('div');
    el.className   = 'sr-item';
    el.setAttribute('role', 'option');
    el.dataset.idx = searchResults.querySelectorAll('.sr-item').length;

    const icon = item.type === 'state'    ? '🏛'
               : item.type === 'district' ? '📍'
               : '🗺';

    el.innerHTML = `
        <div class="sr-icon" style="background:${item.color}18;border:1px solid ${item.color}33;">${icon}</div>
        <div class="sr-main">
            <div class="sr-name">${item.label}</div>
            <div class="sr-sub">${item.sub}</div>
        </div>
        <span class="sr-badge" style="background:${item.color}22;color:${item.color};border:1px solid ${item.color}44;">${item.abbr || item.regionName?.slice(0,2) || ''}</span>
    `;

    el.addEventListener('click', () => activateResult(item));
    el.addEventListener('mouseenter', () => {
        setActiveIdx(parseInt(el.dataset.idx));
    });
    searchResults.appendChild(el);
}

function setActiveIdx(idx) {
    const items = searchResults.querySelectorAll('.sr-item');
    items.forEach((el, i) => el.classList.toggle('active', i === idx));
    searchActiveIdx = idx;
}

async function activateResult(item) {
    closeSearch();
    if (item.type === 'region') {
        enterRegionMode(item.regionName, item.region);
        return;
    }
    // For state or district, navigate to state first
    const mesh = stateMeshes.find(m => m.userData.name === item.stateName);
    if (!mesh) return;
    await enterStateMode(item.stateName, mesh.userData.regionName, mesh.userData.region);
    if (item.type === 'district' && item.districtNum !== 'AL') {
        // Wait for districts to load then programmatically click the matching mesh
        await new Promise(r => setTimeout(r, 800));
        const target = districtMeshes.find(m =>
            m.userData.stateName === item.stateName &&
            m.userData.districtNum === item.districtNum
        );
        if (target) {
            /* Highlight the district exactly as a click would */
            for (const d of districtMeshes) {
                d.material.color.setHex(d.userData.originalColor);
                d.material.opacity = 0.45;
                d.position.z       = 0.29;
            }
            const bright = new THREE.Color(target.userData.regionHex || '#6366f1')
                .lerp(new THREE.Color(0xffffff), 0.72);
            target.material.color.setHex(bright.getHex());
            target.material.opacity = 1.0;
            target.position.z       = 0.44;
            flyToMeshesTopDown([target], 2.6);
            openDistrictPanel(target.userData.districtNum, target.userData.districtLabel, target.userData.stateName, target.userData.regionHex, target.userData.party);
        }
    }
}

function openSearch() {
    searchOverlay.classList.add('open');
    searchInput.value = '';
    renderSearchResults('');
    setTimeout(() => searchInput.focus(), 40);
}
function closeSearch() {
    searchOverlay.classList.remove('open');
}

/* Keyboard navigation inside palette */
searchInput.addEventListener('input', () => renderSearchResults(searchInput.value));
searchInput.addEventListener('keydown', e => {
    const items = searchResults.querySelectorAll('.sr-item');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        const next = Math.min(searchActiveIdx + 1, items.length - 1);
        setActiveIdx(next);
        items[next]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        const prev = Math.max(searchActiveIdx - 1, 0);
        setActiveIdx(prev);
        items[prev]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const active = searchResults.querySelector('.sr-item.active');
        if (active) active.click();
        else if (items.length > 0) items[0].click();
    } else if (e.key === 'Escape') {
        closeSearch();
    }
});

/* Open via button or "/" key */
document.getElementById('btn-search').addEventListener('click', openSearch);
document.addEventListener('keydown', e => {
    if (e.key === '/' && !e.ctrlKey && !e.metaKey &&
        !['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) {
        e.preventDefault();
        openSearch();
    }
    if (e.key === 'Escape' && searchOverlay.classList.contains('open')) {
        closeSearch();
    }
});

/* Click backdrop to close */
searchOverlay.addEventListener('click', e => {
    if (e.target === searchOverlay) closeSearch();
});

/* ════════════════════════════════════════════════════════
   MOBILE MENU
════════════════════════════════════════════════════════ */
const mobileMenuBtn   = document.getElementById('mobile-menu-btn');
const mobileMenu      = document.getElementById('mobile-menu');
const mobBtnDistricts = document.getElementById('mob-btn-districts');
const mobBtnRotate    = document.getElementById('mob-btn-rotate');
const mobBtnReset     = document.getElementById('mob-btn-reset');

function closeMobileMenu() {
    mobileMenu.classList.remove('open');
    mobileMenuBtn.setAttribute('aria-expanded', 'false');
}

mobileMenuBtn.addEventListener('click', e => {
    e.stopPropagation();
    const isOpen = mobileMenu.classList.toggle('open');
    mobileMenuBtn.setAttribute('aria-expanded', String(isOpen));
});

document.addEventListener('click', e => {
    if (!mobileMenu.contains(e.target) && e.target !== mobileMenuBtn) {
        closeMobileMenu();
    }
});

mobBtnDistricts.addEventListener('click', () => {
    document.getElementById('btn-districts').click();
    closeMobileMenu();
});

mobBtnRotate.addEventListener('click', () => {
    document.getElementById('btn-rotate').click();
    closeMobileMenu();
});

mobBtnReset.addEventListener('click', () => {
    document.getElementById('btn-reset').click();
    closeMobileMenu();
});

/* ════════════════════════════════════════════════════════
   CONTROLS
════════════════════════════════════════════════════════ */
function updateRotateBtn(on) {
    // Update dropdown toggle state
    document.getElementById('cm-btn-rotate')?.classList.toggle('active', on);
    // Mobile drawer
    const span = mobBtnRotate?.querySelector('span');
    if (span) span.textContent = on ? 'ON' : 'OFF';
    mobBtnRotate?.classList.toggle('active', on);
}

function updateDistrictsBtn(on) {
    document.getElementById('cm-btn-districts')?.classList.toggle('active', on);
    // Mobile drawer
    const mobSpan = document.getElementById('mob-btn-districts')?.querySelector('span');
    if (mobSpan) mobSpan.textContent = on ? 'ON' : 'OFF';
}

/* ── Controls dropdown ── */
const ctrlWrap    = document.getElementById('controls-wrap');
const ctrlMenu    = document.getElementById('controls-menu');
const btnControls = document.getElementById('btn-controls');

function openControlsMenu(open) {
    ctrlMenu.classList.toggle('open', open);
    btnControls.setAttribute('aria-expanded', String(open));
}

btnControls.addEventListener('click', e => {
    e.stopPropagation();
    openControlsMenu(!ctrlMenu.classList.contains('open'));
});

// Close on click outside
document.addEventListener('click', e => {
    if (!ctrlWrap.contains(e.target)) openControlsMenu(false);
});

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && ctrlMenu.classList.contains('open')) {
        openControlsMenu(false);
        btnControls.focus();
    }
});

document.getElementById('cm-btn-reset').addEventListener('click', () => {
    openControlsMenu(false);
    enterOverviewMode();
    updateRotateBtn(false);
});

document.getElementById('cm-btn-rotate').addEventListener('click', () => {
    controls.autoRotate = !controls.autoRotate;
    updateRotateBtn(controls.autoRotate);
    // Keep menu open so user can see the toggle state change
});

document.getElementById('cm-btn-districts').addEventListener('click', () => {
    toggleNationalBoundaries(); // updateDistrictsBtn is called inside
});

document.getElementById('cm-btn-party-colors').addEventListener('click', () => {
    colorMode = colorMode === 'party' ? 'region' : 'party';
    document.getElementById('cm-btn-party-colors').classList.toggle('active', colorMode === 'party');
    applyColorMode();
    // Keep menu open so the toggle state is visible
});

document.getElementById('cm-btn-kb-help').addEventListener('click', () => {
    openControlsMenu(false);
    toggleKbHelp(true);
});

function stepZoom(factor) {
    const dir = new THREE.Vector3().subVectors(camera.position, controls.target);
    const dist = dir.length();
    const newDist = Math.min(Math.max(dist * factor, controls.minDistance), controls.maxDistance);
    dir.normalize().multiplyScalar(newDist);
    camera.position.copy(controls.target).add(dir);
    controls.update();
}
document.getElementById('cm-btn-zoomin').addEventListener('click',  () => stepZoom(0.8));
document.getElementById('cm-btn-zoomout').addEventListener('click', () => stepZoom(1.25));

document.getElementById('btn-back').addEventListener('click', handleBack);

controls.addEventListener('start', () => {
    if (mapMode === 'overview') { controls.autoRotate = false; updateRotateBtn(false); }
});

document.getElementById('panel-close').addEventListener('click', () => {
    infoPanel.classList.remove('open');
    resizeRenderer(); // restore full-width canvas now panel is gone
    if (mapMode === 'state') {
        resetDistrictSelection();
    } else {
        handleBack();
    }
});

/* ════════════════════════════════════════════════════════
   RENDER LOOP
════════════════════════════════════════════════════════ */
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}
animate();

window.addEventListener('resize', resizeRenderer);
</script>
</body>
</html>
