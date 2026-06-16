<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>U.S. Regional Map – {{ config('app.name', 'U9itus') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    {{-- Three.js import map (must be first) --}}
    <script type="importmap">
    {
      "imports": {
        "three": "https://cdn.jsdelivr.net/npm/three@0.164.1/build/three.module.js",
        "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.164.1/examples/jsm/"
      }
    }
    </script>

    {{-- UMD globals loaded before the module script --}}
    <script src="https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-geo@3.1.0/dist/d3-geo.umd.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #06091a; font-family: 'Inter', system-ui, sans-serif; }
        canvas { display: block; }

        /* ── Top Bar ── */
        #top-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
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

        /* ── Loading ── */
        #loading {
            position: fixed; inset: 0; z-index: 200;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: #06091a; transition: opacity 0.5s ease;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 1s linear infinite; }

        /* ── Tooltip ── */
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

        /* ── Legend ── */
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
            transition: opacity 0.15s;
        }
        .legend-row:last-child { margin-bottom: 0; }
        .legend-swatch {
            width: 13px; height: 13px; border-radius: 3px;
            margin-right: 10px; flex-shrink: 0;
        }
        .legend-name { color: #cbd5e1; font-size: 13px; }
        .legend-count { color: #475569; font-size: 11px; margin-left: 6px; }

        /* ── Info Panel ── */
        #info-panel {
            position: fixed; top: 0; right: 0; bottom: 0;
            width: 300px; z-index: 50;
            background: rgba(8, 12, 28, 0.97);
            border-left: 1px solid rgba(99, 102, 241, 0.2);
            backdrop-filter: blur(16px);
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
            padding: 70px 24px 28px;
            overflow-y: auto;
        }
        #info-panel.open { transform: translateX(0); }
        #panel-close {
            position: absolute; top: 60px; right: 20px;
            background: none; border: none; color: #475569;
            font-size: 22px; cursor: pointer; line-height: 1;
            padding: 4px; transition: color 0.15s;
        }
        #panel-close:hover { color: #94a3b8; }
        .panel-label { color: #475569; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 10px; }
        .panel-divider { border: none; border-top: 1px solid rgba(99,102,241,0.15); margin: 20px 0; }
        .state-chip {
            display: inline-block; padding: 3px 9px;
            border-radius: 999px; font-size: 11px;
            margin: 3px 3px 3px 0;
            border: 1px solid rgba(99,102,241,0.2);
            color: #64748b;
            transition: color 0.15s, border-color 0.15s;
        }
        .state-chip.active { color: #a5b4fc; border-color: rgba(99,102,241,0.5); font-weight: 600; }

        /* ── Hint ── */
        #hint {
            position: fixed; bottom: 28px; right: 24px; z-index: 50;
            color: #334155; font-size: 11px; text-align: right;
            pointer-events: none;
        }

        /* ── Scroll pulse on selected state ── */
        @keyframes pulseRing {
            0%   { transform: scale(1);   opacity: 0.7; }
            100% { transform: scale(1.6); opacity: 0; }
        }
    </style>
</head>
<body>

{{-- Loading --}}
<div id="loading">
    <svg class="spinner" width="44" height="44" viewBox="0 0 24 24" fill="none" style="color:#6366f1;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
    </svg>
    <p style="color:#475569; font-size:13px; margin-top:14px;">Loading map data…</p>
</div>

{{-- Three.js canvas lives here --}}
<div id="map-container" style="position:fixed; inset:0;"></div>

{{-- Top bar --}}
<div id="top-bar">
    <div style="display:flex; align-items:center;">
        <a href="{{ url('/') }}">U9itus</a>
        <span class="sep">|</span>
        <span class="title">U.S. Regional Map</span>
    </div>
    <div style="display:flex; gap:8px;">
        <button class="top-btn" id="btn-reset">Reset View</button>
        <button class="top-btn" id="btn-rotate">Auto-Rotate: ON</button>
        <a href="{{ route('politicians.directory') }}" class="top-btn" style="text-decoration:none;">Browse Politicians →</a>
    </div>
</div>

{{-- Tooltip --}}
<div id="tooltip"></div>

{{-- Legend --}}
<div id="legend">
    <h3>U.S. Regions</h3>
    <div id="legend-items"></div>
</div>

{{-- Info Panel --}}
<div id="info-panel">
    <button id="panel-close">✕</button>
    <div id="panel-content">
        <h2 id="panel-state" style="color:#e2e8f0; font-size:22px; font-weight:700; margin:0 0 10px;"></h2>
        <div id="panel-badge" style="display:inline-block; padding:4px 14px; border-radius:999px; font-size:12px; font-weight:600; margin-bottom:18px;"></div>
        <p id="panel-desc" style="color:#64748b; font-size:13px; line-height:1.6; margin:0;"></p>
        <hr class="panel-divider">
        <p class="panel-label">States in this Region</p>
        <div id="panel-states"></div>
        <hr class="panel-divider">
        <a id="panel-cta" href="#" style="display:block; text-align:center; background:rgba(99,102,241,0.15); border:1px solid rgba(99,102,241,0.35); color:#818cf8; padding:11px; border-radius:9px; font-size:13px; font-weight:600; text-decoration:none; transition:background 0.15s;"
           onmouseover="this.style.background='rgba(99,102,241,0.28)'" onmouseout="this.style.background='rgba(99,102,241,0.15)'">
            Find Politicians in This State →
        </a>
    </div>
</div>

{{-- Hint --}}
<div id="hint">
    Drag to rotate &nbsp;·&nbsp; Scroll to zoom &nbsp;·&nbsp; Click a state
</div>

<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/* ════════════════════════════════════════════════════════════
   REGION DATA
════════════════════════════════════════════════════════════ */
const REGIONS = {
    Northeast: {
        states: ['Connecticut','Maine','Massachusetts','New Hampshire','New Jersey',
                 'New York','Pennsylvania','Rhode Island','Vermont'],
        color:  0x6366f1,
        colorH: 0x7c7ff5,   // hover / highlight
        hex:    '#6366f1',
        label:  'Northeast'
    },
    Midwest: {
        states: ['Illinois','Indiana','Iowa','Kansas','Michigan','Minnesota',
                 'Missouri','Nebraska','North Dakota','Ohio','South Dakota','Wisconsin'],
        color:  0xf59e0b,
        colorH: 0xfbbf24,
        hex:    '#f59e0b',
        label:  'Midwest'
    },
    South: {
        states: ['Alabama','Arkansas','Delaware','Florida','Georgia','Kentucky',
                 'Louisiana','Maryland','Mississippi','North Carolina','Oklahoma',
                 'South Carolina','Tennessee','Texas','Virginia','West Virginia',
                 'District of Columbia'],
        color:  0xef4444,
        colorH: 0xf87171,
        hex:    '#ef4444',
        label:  'South'
    },
    West: {
        states: ['Alaska','Arizona','California','Colorado','Hawaii','Idaho',
                 'Montana','Nevada','New Mexico','Oregon','Utah','Washington','Wyoming'],
        color:  0x10b981,
        colorH: 0x34d399,
        hex:    '#10b981',
        label:  'West'
    }
};

const stateToRegion = {};
for (const [rName, rData] of Object.entries(REGIONS)) {
    for (const s of rData.states) stateToRegion[s] = rName;
}

/* ════════════════════════════════════════════════════════════
   SCENE / RENDERER / CAMERA
════════════════════════════════════════════════════════════ */
const container = document.getElementById('map-container');

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x06091a);
scene.fog = new THREE.FogExp2(0x060914, 0.014);

const W = () => container.clientWidth;
const H = () => container.clientHeight;

const camera = new THREE.PerspectiveCamera(42, W() / H(), 0.1, 300);
camera.position.set(0, 7, 13);

const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(W(), H());
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
container.appendChild(renderer.domElement);

/* ── Lighting ── */
scene.add(new THREE.AmbientLight(0x6080b0, 0.65));

const sun = new THREE.DirectionalLight(0xffffff, 1.1);
sun.position.set(6, 14, 10);
scene.add(sun);

const fill = new THREE.DirectionalLight(0x304080, 0.35);
fill.position.set(-8, -4, -12);
scene.add(fill);

/* ── Orbit Controls ── */
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping   = true;
controls.dampingFactor   = 0.07;
controls.minDistance     = 5;
controls.maxDistance     = 45;
controls.maxPolarAngle   = Math.PI / 2.1;
controls.autoRotate      = true;
controls.autoRotateSpeed = 0.4;
controls.target.set(0, 0, 0);

/* ── Stars ── */
const starPositions = new Float32Array(2000 * 3);
for (let i = 0; i < 2000; i++) {
    const r = 80 + Math.random() * 100;
    const theta = Math.random() * Math.PI * 2;
    const phi   = Math.acos(2 * Math.random() - 1);
    starPositions[i*3]   = r * Math.sin(phi) * Math.cos(theta);
    starPositions[i*3+1] = r * Math.sin(phi) * Math.sin(theta);
    starPositions[i*3+2] = r * Math.cos(phi);
}
const starGeo = new THREE.BufferGeometry();
starGeo.setAttribute('position', new THREE.BufferAttribute(starPositions, 3));
scene.add(new THREE.Points(starGeo, new THREE.PointsMaterial({ color: 0x8899cc, size: 0.18, sizeAttenuation: true })));

/* ── Thin base plane (ocean) ── */
const ocean = new THREE.Mesh(
    new THREE.PlaneGeometry(22, 14),
    new THREE.MeshPhongMaterial({ color: 0x0d1a36, shininess: 10, side: THREE.FrontSide })
);
ocean.rotation.x = 0; // already in XY plane via the map layout; keep flat
scene.add(ocean);

/* ════════════════════════════════════════════════════════════
   MAP PROJECTION
════════════════════════════════════════════════════════════ */
const GEO_SCALE     = 1070;
const GEO_TRANSLATE = [480, 300];
const NORM          = 82;   // divide pixel-space to get ±~5.85 Three.js units

const projection = d3.geoAlbersUsa()
    .scale(GEO_SCALE)
    .translate(GEO_TRANSLATE);

function project([lon, lat]) {
    const p = projection([lon, lat]);
    if (!p) return null;
    return [(p[0] - GEO_TRANSLATE[0]) / NORM,
            -(p[1] - GEO_TRANSLATE[1]) / NORM];
}

/* ════════════════════════════════════════════════════════════
   GEOMETRY BUILDER
════════════════════════════════════════════════════════════ */
const mapGroup  = new THREE.Group();
scene.add(mapGroup);

const stateMeshes = [];   // for raycasting (filled faces only)

function buildState(feature) {
    const name       = feature.properties.name;
    const regionName = stateToRegion[name];
    const region     = REGIONS[regionName];
    const color      = region ? region.color : 0x334155;

    const polys = feature.geometry.type === 'MultiPolygon'
        ? feature.geometry.coordinates
        : [feature.geometry.coordinates];

    const group = new THREE.Group();
    group.userData.stateName = name;

    for (const poly of polys) {
        const outerRing = poly[0];
        const p0 = project(outerRing[0]);
        if (!p0) continue;

        const shape = new THREE.Shape();
        shape.moveTo(p0[0], p0[1]);
        for (let i = 1; i < outerRing.length; i++) {
            const p = project(outerRing[i]);
            if (p) shape.lineTo(p[0], p[1]);
        }
        shape.closePath();

        for (let h = 1; h < poly.length; h++) {
            const hp0 = project(poly[h][0]);
            if (!hp0) continue;
            const hole = new THREE.Path();
            hole.moveTo(hp0[0], hp0[1]);
            for (let i = 1; i < poly[h].length; i++) {
                const p = project(poly[h][i]);
                if (p) hole.lineTo(p[0], p[1]);
            }
            hole.closePath();
            shape.holes.push(hole);
        }

        /* Filled extruded mesh */
        const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.28, bevelEnabled: false });
        const mat = new THREE.MeshPhongMaterial({
            color,
            shininess: 25,
            specular:  new THREE.Color(0x1a2a44),
        });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData = { name, regionName, region, originalColor: color };
        group.add(mesh);
        stateMeshes.push(mesh);

        /* Border outline */
        const edgeGeo = new THREE.EdgesGeometry(geo, 2);
        const edgeMat = new THREE.LineBasicMaterial({ color: 0x090d1f, transparent: true, opacity: 0.85 });
        group.add(new THREE.LineSegments(edgeGeo, edgeMat));
    }

    return group;
}

/* ════════════════════════════════════════════════════════════
   LOAD MAP DATA
════════════════════════════════════════════════════════════ */
const loadingEl = document.getElementById('loading');

fetch('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json')
    .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
    .then(us => {
        const statesGeo = topojson.feature(us, us.objects.states);
        for (const feature of statesGeo.features) {
            const group = buildState(feature);
            mapGroup.add(group);
        }
        buildLegend();
        loadingEl.style.opacity = '0';
        setTimeout(() => { loadingEl.style.display = 'none'; }, 520);
    })
    .catch(err => {
        console.error('Map load failed:', err);
        loadingEl.innerHTML = '<p style="color:#ef4444; font-size:14px;">Failed to load map data.<br>Check your connection.</p>';
    });

/* ════════════════════════════════════════════════════════════
   LEGEND
════════════════════════════════════════════════════════════ */
function buildLegend() {
    const el = document.getElementById('legend-items');
    el.innerHTML = '';
    for (const [name, data] of Object.entries(REGIONS)) {
        const row = document.createElement('div');
        row.className = 'legend-row';
        row.innerHTML = `
            <span class="legend-swatch" style="background:${data.hex};"></span>
            <span class="legend-name">${name}</span>
            <span class="legend-count">(${data.states.length})</span>
        `;
        row.addEventListener('mouseenter', () => dimExcept(name));
        row.addEventListener('mouseleave', clearDim);
        row.addEventListener('click', () => {
            // Focus camera on region centroid (rough center)
            controls.autoRotate = false;
            updateRotateBtn(false);
        });
        el.appendChild(row);
    }
}

/* ════════════════════════════════════════════════════════════
   HOVER / CLICK INTERACTION
════════════════════════════════════════════════════════════ */
const tooltip   = document.getElementById('tooltip');
const infoPanel = document.getElementById('info-panel');
const raycaster = new THREE.Raycaster();
const mouse     = new THREE.Vector2();

let hoveredMesh   = null;
let selectedState = null;

function lighten(hex, amt = 55) {
    const r = Math.min(255, ((hex >> 16) & 0xff) + amt);
    const g = Math.min(255, ((hex >> 8)  & 0xff) + amt);
    const b = Math.min(255, ( hex        & 0xff)  + amt);
    return (r << 16) | (g << 8) | b;
}

function dimExcept(regionName) {
    for (const m of stateMeshes) {
        if (m.userData.regionName !== regionName) {
            m.material.color.setHex(0x1a2240);
        } else {
            m.material.color.setHex(lighten(m.userData.originalColor, 30));
        }
    }
}

function clearDim() {
    for (const m of stateMeshes) {
        const isHovered   = m === hoveredMesh;
        const isSelected  = m.userData.name === selectedState;
        if (!isHovered && !isSelected) {
            m.material.color.setHex(m.userData.originalColor);
        }
    }
}

renderer.domElement.addEventListener('mousemove', e => {
    const rect = renderer.domElement.getBoundingClientRect();
    mouse.x =  ((e.clientX - rect.left) / rect.width)  * 2 - 1;
    mouse.y = -((e.clientY - rect.top)  / rect.height)  * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(stateMeshes);

    /* Restore previous hover */
    if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
        hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
        hoveredMesh.parent.position.z = 0;
    }

    if (hits.length > 0) {
        const mesh = hits[0].object;
        hoveredMesh = mesh;

        if (mesh.userData.name !== selectedState) {
            mesh.material.color.setHex(lighten(mesh.userData.originalColor, 50));
        }
        mesh.parent.position.z = 0.18;

        tooltip.style.display = 'block';
        tooltip.style.left    = (e.clientX + 16) + 'px';
        tooltip.style.top     = (e.clientY - 14) + 'px';
        tooltip.innerHTML     = `
            <strong style="color:#e2e8f0; display:block; margin-bottom:3px;">${mesh.userData.name}</strong>
            <span style="color:${mesh.userData.region?.hex || '#888'}; font-size:12px;">
                &#9679; ${mesh.userData.regionName || 'Unknown'} Region
            </span>`;
        renderer.domElement.style.cursor = 'pointer';
    } else {
        hoveredMesh = null;
        tooltip.style.display = 'none';
        renderer.domElement.style.cursor = 'default';
    }
});

renderer.domElement.addEventListener('mouseleave', () => {
    if (hoveredMesh && hoveredMesh.userData.name !== selectedState) {
        hoveredMesh.material.color.setHex(hoveredMesh.userData.originalColor);
        hoveredMesh.parent.position.z = 0;
    }
    hoveredMesh = null;
    tooltip.style.display = 'none';
});

renderer.domElement.addEventListener('click', () => {
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(stateMeshes);
    if (hits.length > 0) {
        const mesh = hits[0].object;
        openPanel(mesh.userData.name, mesh.userData.regionName, mesh.userData.region);
        selectedState = mesh.userData.name;

        /* Highlight selected */
        for (const m of stateMeshes) {
            if (m.userData.name !== selectedState) {
                m.material.color.setHex(m.userData.originalColor);
                m.parent.position.z = 0;
            } else {
                m.material.color.setHex(lighten(m.userData.originalColor, 60));
                m.parent.position.z = 0.25;
            }
        }
    }
});

/* ── Info Panel ── */
function openPanel(stateName, regionName, region) {
    document.getElementById('panel-state').textContent = stateName;

    const badge = document.getElementById('panel-badge');
    badge.textContent     = (regionName || 'Unknown') + ' Region';
    badge.style.background = (region?.hex || '#888') + '22';
    badge.style.color      = region?.hex || '#888';
    badge.style.border     = '1px solid ' + (region?.hex || '#888') + '55';

    document.getElementById('panel-desc').textContent =
        `${stateName} is located in the ${regionName || 'Unknown'} region of the United States.`;

    const statesEl = document.getElementById('panel-states');
    statesEl.innerHTML = '';
    for (const s of (region?.states || [])) {
        const chip = document.createElement('span');
        chip.className = 'state-chip' + (s === stateName ? ' active' : '');
        chip.textContent = s;
        chip.title       = s;
        statesEl.appendChild(chip);
    }

    document.getElementById('panel-cta').href =
        `{{ route('politicians.directory') }}?state=${encodeURIComponent(stateName)}`;

    infoPanel.classList.add('open');
}

document.getElementById('panel-close').addEventListener('click', () => {
    infoPanel.classList.remove('open');
    selectedState = null;
    clearDim();
    for (const m of stateMeshes) {
        m.parent.position.z = 0;
    }
});

/* ════════════════════════════════════════════════════════════
   CONTROLS: RESET & AUTO-ROTATE
════════════════════════════════════════════════════════════ */
function updateRotateBtn(on) {
    document.getElementById('btn-rotate').textContent = `Auto-Rotate: ${on ? 'ON' : 'OFF'}`;
}

document.getElementById('btn-reset').addEventListener('click', () => {
    camera.position.set(0, 7, 13);
    controls.target.set(0, 0, 0);
    controls.autoRotate = true;
    updateRotateBtn(true);
    controls.update();
});

document.getElementById('btn-rotate').addEventListener('click', () => {
    controls.autoRotate = !controls.autoRotate;
    updateRotateBtn(controls.autoRotate);
});

/* Pause auto-rotate on user drag */
controls.addEventListener('start', () => {
    controls.autoRotate = false;
    updateRotateBtn(false);
});

/* ════════════════════════════════════════════════════════════
   RENDER LOOP
════════════════════════════════════════════════════════════ */
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}
animate();

/* ── Resize ── */
window.addEventListener('resize', () => {
    camera.aspect = W() / H();
    camera.updateProjectionMatrix();
    renderer.setSize(W(), H());
});
</script>
</body>
</html>
