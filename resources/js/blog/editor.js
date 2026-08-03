import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const AUTOSAVE_INTERVAL_MS = 20000;

/**
 * Wires a Quill editor to a hidden <textarea> for standard form submission.
 * The server independently re-sanitizes whatever HTML this produces
 * (see Post::htmlSanitizer()), so the toolbar here only needs to stay
 * roughly in sync with that allow-list — it isn't the security boundary.
 */
function initPostBodyEditor(container) {
    const hiddenInput = document.getElementById(container.dataset.input);
    const uploadUrl = container.dataset.uploadUrl;
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
                    ['link', 'image'],
                    ['clean'],
                ],
                handlers: {
                    image: () => selectAndUploadImage(quill, uploadUrl),
                },
            },
        },
    });

    if (hiddenInput.value) {
        quill.clipboard.dangerouslyPasteHTML(hiddenInput.value);
    }

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
