import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const AUTOSAVE_INTERVAL_MS = 20000;

// Custom toolbar icon for the embed button — matches Quill's own SVG style
// (ql-stroke class) so the dark-theme color overrides in app.css apply to it
// automatically, same as every built-in icon.
const icons = Quill.import('ui/icons');
icons.embed =
    '<svg viewBox="0 0 18 18">' +
    '<polyline class="ql-stroke" points="5 7 3 9 5 11"></polyline>' +
    '<polyline class="ql-stroke" points="13 7 15 9 13 11"></polyline>' +
    '<line class="ql-stroke" x1="10" x2="8" y1="5" y2="13"></line>' +
    '</svg>';

const Delta = Quill.import('delta');

/**
 * Wires a Quill editor to a hidden <textarea> for standard form submission.
 * The server independently re-sanitizes whatever HTML this produces
 * (see Post::htmlSanitizer()), so the toolbar here only needs to stay
 * roughly in sync with that allow-list — it isn't the security boundary.
 */
function initPostBodyEditor(container) {
    const hiddenInput = document.getElementById(container.dataset.input);
    const uploadUrl = container.dataset.uploadUrl;
    const embedUrl = container.dataset.embedUrl;
    // The layout splits fields across a form-attribute pattern (see
    // _form.blade.php) rather than literal DOM nesting, so the editor isn't
    // actually inside <form id="post-form"> — look it up by id instead.
    const form = document.getElementById('post-form');

    const quill = new Quill(container, {
        theme: 'snow',
        placeholder: 'Write your post here...',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link', 'image', 'embed'],
                    ['clean'],
                ],
                handlers: {
                    image: () => selectAndUploadImage(quill, uploadUrl),
                    embed: () => promptAndInsertEmbed(quill, embedUrl),
                },
            },
        },
    });

    if (hiddenInput.value) {
        quill.clipboard.dangerouslyPasteHTML(hiddenInput.value);
    }

    registerPasteCleanupMatchers(quill);

    quill.on('text-change', () => {
        hiddenInput.value = quill.root.innerHTML;
        // Programmatic .value assignment doesn't fire a native 'input' event,
        // so dispatch one — autosave's dirty-tracking listens for it.
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    form?.addEventListener('submit', () => {
        hiddenInput.value = quill.root.innerHTML;
    });

    if (form) {
        initAutosave(form);
    }
}

/**
 * Cleans up pasted content at the point Quill itself converts clipboard HTML
 * to a Delta (quill.clipboard.addMatcher — not a raw 'paste' DOM listener,
 * which would run alongside Quill's own built-in paste handler on the same
 * element and double-insert). External sources (Wix in particular) paste
 * deeply-nested markup full of inline colors/fonts and empty paragraphs used
 * as manual visual spacers. The server independently strips all of that on
 * save (see Post::sanitizeBody()) regardless of what these matchers do — this
 * exists so the composer shows authors what will actually be saved, instead
 * of a misleading preview that reverts on the next save.
 */
function registerPasteCleanupMatchers(quill) {
    // Quill's default build recognizes color/background/font/size as paste
    // formats even though this editor's toolbar never exposes them, so
    // pasted colors/fonts otherwise render (and look "applied") in the
    // composer right up until the server strips them on save.
    quill.clipboard.addMatcher(Node.ELEMENT_NODE, (_node, delta) => {
        delta.ops.forEach((op) => {
            if (op.attributes) {
                delete op.attributes.color;
                delete op.attributes.background;
                delete op.attributes.font;
                delete op.attributes.size;
            }
        });
        return delta;
    });

    // Wix (and similar page builders) use empty paragraphs/divs as manual
    // visual spacers. Drop any block whose content is blank once resolved
    // to a Delta — matches Post::sanitizeBody()'s server-side stripping.
    const dropIfBlank = (_node, delta) => (isBlankDelta(delta) ? new Delta() : delta);
    quill.clipboard.addMatcher('p', dropIfBlank);
    quill.clipboard.addMatcher('div', dropIfBlank);
}

function isBlankDelta(delta) {
    return delta.ops.every((op) => {
        if (typeof op.insert !== 'string') {
            return false; // an embed (image, embed block, etc.) — not blank
        }
        return op.insert.trim() === '';
    });
}

function selectAndUploadImage(quill, uploadUrl) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp';

    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file || !uploadUrl) {
            return;
        }

        const range = quill.getSelection(true);
        const placeholderText = 'Uploading image…';
        quill.insertText(range.index, placeholderText, 'italic', true);

        let placeholderRemoved = false;
        const removePlaceholder = () => {
            if (!placeholderRemoved) {
                placeholderRemoved = true;
                quill.deleteText(range.index, placeholderText.length);
            }
        };

        const body = new FormData();
        body.append('image', file);

        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body,
            });

            if (!response.ok) {
                throw new Error('Upload failed');
            }

            const { url } = await response.json();
            removePlaceholder();
            quill.insertEmbed(range.index, 'image', url, 'user');
            quill.setSelection(range.index + 1, 0, 'user');
        } catch (err) {
            removePlaceholder();
            console.error('Post image upload failed', err);
            window.alert('Sorry, that image could not be uploaded. Please try a different file.');
        }
    };

    input.click();
}

/**
 * Prompts for a YouTube / Instagram / SoundCloud / X / TikTok URL, resolves
 * it to safe embed HTML server-side (PostEmbedService — the client never
 * builds or trusts embed markup itself), and inserts the result.
 */
async function promptAndInsertEmbed(quill, embedUrl) {
    if (!embedUrl) {
        return;
    }

    const url = window.prompt('Paste a YouTube, Instagram, SoundCloud, X, or TikTok link:');
    if (!url || !url.trim()) {
        return;
    }

    const range = quill.getSelection(true);
    const placeholderText = 'Loading embed…';
    quill.insertText(range.index, placeholderText, 'italic', true);

    let placeholderRemoved = false;
    const removePlaceholder = () => {
        if (!placeholderRemoved) {
            placeholderRemoved = true;
            quill.deleteText(range.index, placeholderText.length);
        }
    };

    try {
        const response = await fetch(embedUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ url: url.trim() }),
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.html) {
            throw new Error(payload?.message ?? '');
        }

        removePlaceholder();
        quill.clipboard.dangerouslyPasteHTML(range.index, payload.html, 'user');
    } catch (err) {
        removePlaceholder();
        console.error('Post embed failed', err);
        window.alert(
            err.message || "Sorry, that link couldn't be embedded. Please check the URL and try again."
        );
    }
}

/**
 * Periodically PUTs the post form in the background once it has something
 * worth saving. Only runs in edit mode (data-autosave-url is only rendered
 * once a post exists — there's nothing to PUT to on the create page until
 * the first manual "Save Draft"). Never touches publish status: it hits the
 * same update() action the manual "Update Post" button does.
 */
function initAutosave(form) {
    if (form.dataset.autosave !== '1') {
        return; // create mode — no post to PUT to yet
    }

    const statusEl = document.getElementById('autosave-status');
    let dirty = false;
    let saving = false;

    const markDirty = (event) => {
        if (event.target.form === form) {
            dirty = true;
        }
    };
    document.addEventListener('input', markDirty);
    document.addEventListener('change', markDirty);

    const setStatus = (text) => {
        if (statusEl) {
            statusEl.textContent = text;
        }
    };

    /**
     * Post::boot() regenerates the slug whenever the title changes, so every
     * slug-keyed URL on the page (this form's own action included) can go
     * stale mid-session. Patch them from the server's response rather than
     * letting the next background save — or a Publish/Archive click — 404
     * against a slug that no longer exists.
     */
    const syncStaleUrls = (urls) => {
        if (!urls) {
            return;
        }
        form.action = urls.updateUrl;
        window.history.replaceState(null, '', urls.editUrl);

        const publishForm = document.getElementById('publish-form');
        if (publishForm) {
            publishForm.action = urls.publishUrl;
        }
        const archiveForm = document.getElementById('archive-form');
        if (archiveForm) {
            archiveForm.action = urls.archiveUrl;
        }
        const publicLink = document.getElementById('view-public-link');
        if (publicLink) {
            publicLink.href = urls.publicUrl;
        }
    };

    const attempt = async () => {
        if (!dirty || saving) {
            return;
        }

        const formData = new FormData(form);
        if (!formData.get('title')?.toString().trim()) {
            // Matches the server's required:title rule — don't burn a request
            // (and don't show a scary "failed" status) on an untitled draft.
            return;
        }
        formData.set('_background_save', '1');

        saving = true;
        setStatus('Saving…');

        try {
            const response = await fetch(form.action, {
                method: 'POST', // form already carries _method=PUT
                body: formData,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.ok) {
                dirty = false;
                syncStaleUrls(await response.json().catch(() => null));
                const time = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                setStatus(`Saved ${time}`);
            } else {
                setStatus('Autosave failed');
            }
        } catch (err) {
            console.error('Autosave failed', err);
            setStatus('Autosave failed');
        } finally {
            saving = false;
        }
    };

    setInterval(attempt, AUTOSAVE_INTERVAL_MS);
    window.addEventListener('beforeunload', () => {
        if (dirty && !saving) {
            const formData = new FormData(form);
            formData.set('_background_save', '1');
            navigator.sendBeacon?.(form.action, formData);
        }
    });
}

document.querySelectorAll('[data-post-body-editor]').forEach(initPostBodyEditor);
