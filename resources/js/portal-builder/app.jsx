/**
 * Portal builder / public-render entry — isolated bundle, mirrors
 * resources/js/map/app.js: its own Vite entry, its own CSS, no dependency
 * on the global app.js/Alpine bundle (see resources/views/standalone/portal/
 * builder.blade.php and show.blade.php, which are standalone HTML shells
 * like standalone/public/us-map.blade.php).
 *
 * Uses @puckeditor/core — the actively maintained continuation of
 * @measured/puck, which is deprecated as of this writing.
 *
 * One bundle serves two modes, switched by the mount div's data-mode:
 *   "builder" -> <Puck> drag-and-drop editor (org-owner only)
 *   "render"  -> <Render> read-only (public portal page + embed)
 * Both use the same puckConfig, so a block renders identically in the
 * editor's live preview and on the published page.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { Puck, Render } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import './portal.css';
import { puckConfig, setPortalData, setCanEndorseCandidates } from './blocks.jsx';

const root = document.getElementById('portal-root');
if (root) {
    const mode = root.dataset.mode;
    const payload = JSON.parse(document.getElementById('portal-builder-data').textContent);

    setPortalData(payload.portalData);
    setCanEndorseCandidates(payload.canEndorseCandidates);

    const reactRoot = createRoot(root);

    if (mode === 'builder') {
        mountBuilder(reactRoot, payload);
    } else {
        reactRoot.render(<Render config={puckConfig} data={payload.initialData || { content: [], root: {} }} />);
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function mountBuilder(reactRoot, payload) {
    let latestData = payload.initialData || { content: [], root: {} };
    let dirty = false;

    const save = async (data, { publish } = {}) => {
        const body = { portal_layout: data };
        if (publish !== undefined) body.portal_published = publish;

        const response = await fetch(payload.saveUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body),
        });

        dirty = false;
        return response.ok;
    };

    // Same 20s dirty-tracked autosave cadence as resources/js/blog/editor.js's
    // initAutosave() — background save, no publish-state change.
    setInterval(() => {
        if (dirty) save(latestData);
    }, 20000);

    window.addEventListener('beforeunload', () => {
        if (dirty) {
            navigator.sendBeacon(
                payload.saveUrl,
                new Blob([JSON.stringify({ portal_layout: latestData, _method: 'PUT' })], { type: 'application/json' })
            );
        }
    });

    reactRoot.render(
        <Puck
            config={puckConfig}
            data={latestData}
            onChange={(data) => {
                latestData = data;
                dirty = true;
            }}
            onPublish={async (data) => {
                const ok = await save(data, { publish: true });
                const status = document.getElementById('portal-builder-status');
                if (status) status.textContent = ok ? 'Portal published.' : 'Save failed — try again.';
            }}
        />
    );
}
